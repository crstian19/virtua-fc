<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->index(['win_percentage', 'matches_played'], 'ms_win_pct_mp');
            $table->index(['matches_played'], 'ms_matches_played');
            $table->index(['longest_unbeaten_streak', 'matches_played'], 'ms_streak_mp');
            $table->index(['seasons_completed', 'matches_played'], 'ms_seasons_mp');
        });
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropIndex('ms_win_pct_mp');
            $table->dropIndex('ms_matches_played');
            $table->dropIndex('ms_streak_mp');
            $table->dropIndex('ms_seasons_mp');
        });
    }
};
