<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamH2hMatch extends Model
{
    protected $fillable = [
        'home_team_id', 'away_team_id', 'match_date',
        'home_score', 'away_score', 'competition',
    ];

    protected $casts = [
        'match_date' => 'date',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
