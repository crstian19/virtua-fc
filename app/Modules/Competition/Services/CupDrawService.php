<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Modules\Competition\Services\LeagueFixtureGenerator;

class CupDrawService
{
    /**
     * Conduct a draw for a specific cup round.
     *
     * @return Collection<CupTie>
     */
    public function conductDraw(string $gameId, string $competitionId, int $roundNumber): Collection
    {
        $roundConfig = $this->getRoundConfig($gameId, $competitionId, $roundNumber);

        if (!$roundConfig) {
            throw new \RuntimeException("No knockout round config found for {$competitionId} round {$roundNumber}");
        }

        $competition = Competition::find($competitionId);
        $season = $competition->season ?? '2025';

        // Get all teams eligible for this round
        $teams = $this->getTeamsForRound($gameId, $competitionId, $season, $roundNumber);

        // Shuffle teams for random pairing
        $shuffledTeams = $teams->shuffle();

        $teamCount = $shuffledTeams->count();
        $pairCount = intdiv($teamCount, 2);

        if ($pairCount === 0) {
            return collect();
        }

        // Phase 1: Pre-generate all UUIDs and build row arrays
        $tieRows = [];
        $firstLegRows = [];
        $secondLegRows = [];
        $tieMatchMap = []; // tieId => [firstLegId, secondLegId]

        for ($i = 0; $i < $pairCount; $i++) {
            $homeTeamId = $shuffledTeams[$i * 2];
            $awayTeamId = $shuffledTeams[$i * 2 + 1];

            $tieId = Str::uuid()->toString();
            $firstLegId = Str::uuid()->toString();
            $secondLegId = $roundConfig->twoLegged ? Str::uuid()->toString() : null;

            $tieRows[] = [
                'id' => $tieId,
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $roundNumber,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'completed' => false,
            ];

            $firstLegRows[] = [
                'id' => $firstLegId,
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $roundNumber,
                'round_name' => $roundConfig->name,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'scheduled_date' => $roundConfig->firstLegDate,
                'cup_tie_id' => $tieId,
                'played' => false,
                'is_extra_time' => false,
            ];

            if ($roundConfig->twoLegged) {
                $secondLegRows[] = [
                    'id' => $secondLegId,
                    'game_id' => $gameId,
                    'competition_id' => $competitionId,
                    'round_number' => $roundNumber,
                    'round_name' => $roundConfig->name . '_return',
                    'home_team_id' => $awayTeamId,
                    'away_team_id' => $homeTeamId,
                    'scheduled_date' => $roundConfig->secondLegDate,
                    'cup_tie_id' => $tieId,
                    'played' => false,
                    'is_extra_time' => false,
                ];
            }

            $tieMatchMap[$tieId] = [
                'first_leg_match_id' => $firstLegId,
                'second_leg_match_id' => $secondLegId,
            ];
        }

        // Phase 2: Bulk insert all records
        foreach (array_chunk($tieRows, 100) as $chunk) {
            CupTie::insert($chunk);
        }

        foreach (array_chunk($firstLegRows, 100) as $chunk) {
            GameMatch::insert($chunk);
        }

        if (!empty($secondLegRows)) {
            foreach (array_chunk($secondLegRows, 100) as $chunk) {
                GameMatch::insert($chunk);
            }
        }

        // Phase 3: Bulk update CupTies with match IDs using upsert
        $upsertRows = [];
        foreach ($tieMatchMap as $tieId => $matchIds) {
            $row = ['id' => $tieId, 'first_leg_match_id' => $matchIds['first_leg_match_id']];
            if ($roundConfig->twoLegged) {
                $row['second_leg_match_id'] = $matchIds['second_leg_match_id'];
            }
            $upsertRows[] = $row;
        }

        $updateColumns = $roundConfig->twoLegged
            ? ['first_leg_match_id', 'second_leg_match_id']
            : ['first_leg_match_id'];

        CupTie::upsert($upsertRows, ['id'], $updateColumns);

        // Return loaded ties
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber)
            ->get();
    }

    /**
     * Get all team IDs eligible for a specific round.
     *
     * @return Collection<string>
     */
    private function getTeamsForRound(string $gameId, string $competitionId, string $season, int $roundNumber): Collection
    {
        $teams = collect();

        // Teams entering at this specific round
        $enteringTeams = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('entry_round', $roundNumber)
            ->pluck('team_id');

        $teams = $teams->merge($enteringTeams);

        // Winners from previous round
        if ($roundNumber > 1) {
            $previousWinners = CupTie::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('round_number', $roundNumber - 1)
                ->where('completed', true)
                ->whereNotNull('winner_id')
                ->pluck('winner_id');

            $teams = $teams->merge($previousWinners);
        }

        return $teams->unique()->values();
    }

    /**
     * Check if a draw is needed for a specific round.
     */
    public function needsDrawForRound(string $gameId, string $competitionId, int $roundNumber): bool
    {
        // Check if ties already exist for this round
        $existingTies = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber)
            ->count();

        if ($existingTies > 0) {
            return false;
        }

        // Check if round config exists in schedule.json
        $roundConfig = $this->getRoundConfig($gameId, $competitionId, $roundNumber);

        if (!$roundConfig) {
            return false;
        }

        // For round 1, we just need teams entering at round 1
        if ($roundNumber === 1) {
            $teamsEntering = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('entry_round', 1)
                ->count();

            return $teamsEntering > 0;
        }

        // For later rounds, all previous round ties must be completed
        $previousRoundQuery = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber - 1);

        $totalPreviousTies = $previousRoundQuery->count();

        if ($totalPreviousTies === 0) {
            return false;
        }

        $completedPreviousTies = (clone $previousRoundQuery)->where('completed', true)->count();

        return $totalPreviousTies === $completedPreviousTies;
    }

    /**
     * Get the next round that needs a draw.
     */
    public function getNextRoundNeedingDraw(string $gameId, string $competitionId): ?int
    {
        // Find rounds that already have ties drawn
        $drawnRounds = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->distinct()
            ->pluck('round_number');

        // Load all knockout rounds from schedule.json and find the first undrawn
        $allRounds = $this->getAllRoundConfigs($gameId, $competitionId);

        $nextUndrawnRound = null;
        foreach ($allRounds as $round) {
            if (!$drawnRounds->contains($round->round)) {
                $nextUndrawnRound = $round->round;
                break;
            }
        }

        if ($nextUndrawnRound === null) {
            return null;
        }

        // Verify it's actually ready (previous round complete or it's round 1)
        if ($this->needsDrawForRound($gameId, $competitionId, $nextUndrawnRound)) {
            return $nextUndrawnRound;
        }

        return null;
    }

    /**
     * Get round config from schedule.json for a specific round.
     */
    private function getRoundConfig(string $gameId, string $competitionId, int $roundNumber): ?PlayoffRoundConfig
    {
        $rounds = $this->getAllRoundConfigs($gameId, $competitionId);

        foreach ($rounds as $round) {
            if ($round->round === $roundNumber) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Get all knockout round configs for a competition from schedule.json.
     * Dates are automatically adjusted for the current game season.
     *
     * @return PlayoffRoundConfig[]
     */
    private function getAllRoundConfigs(string $gameId, string $competitionId): array
    {
        $competition = Competition::find($competitionId);
        if (!$competition) {
            return [];
        }

        $gameSeason = Game::where('id', $gameId)->value('season');

        return LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season, $gameSeason);
    }
}
