<?php

namespace App\Console\Commands;

use App\Models\ClubProfile;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\TeamReputation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillTeamReputations extends Command
{
    protected $signature = 'app:backfill-team-reputations';

    protected $description = 'Backfill team_reputations records for games created before the feature existed';

    public function handle(): int
    {
        $games = Game::whereDoesntHave('teamReputations')->get();

        if ($games->isEmpty()) {
            $this->info('All games already have reputation records. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$games->count()} game(s) without reputation records.");

        foreach ($games as $game) {
            $this->backfillGame($game);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function backfillGame(Game $game): void
    {
        $countryCode = $game->country ?? 'ES';

        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->whereHas('competition', fn ($q) => $q->where('country', $countryCode))
            ->pluck('team_id')
            ->unique();

        if ($teamIds->isEmpty()) {
            $this->warn("  Game {$game->id}: no domestic competition entries found, skipping.");

            return;
        }

        $clubProfiles = ClubProfile::whereIn('team_id', $teamIds)
            ->pluck('reputation_level', 'team_id');

        $rows = [];
        foreach ($teamIds as $teamId) {
            $level = $clubProfiles[$teamId] ?? ClubProfile::REPUTATION_LOCAL;
            $points = TeamReputation::pointsForTier($level);

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'team_id' => $teamId,
                'reputation_level' => $level,
                'base_reputation_level' => $level,
                'reputation_points' => $points,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            TeamReputation::insert($chunk);
        }

        $this->info("  Game {$game->id}: created " . count($rows) . ' reputation records.');
    }
}
