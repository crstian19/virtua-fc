<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Cache;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        Cache::forget("game_owner:{$game->id}");

        // Deleting the game cascades to all FK-constrained child tables
        $game->delete();
    }
}
