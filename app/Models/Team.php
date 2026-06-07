<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'api_id', 'name', 'short_name', 'fifa_code',
        'fifa_ranking', 'confederation', 'wc_appearances',
        'wc_best_result', 'avg_goals_scored_wc', 'avg_goals_conceded_wc',
        'flag_emoji',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_team_id');
    }
}
