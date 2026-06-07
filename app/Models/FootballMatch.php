<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FootballMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'api_id', 'home_team_id', 'away_team_id', 'match_date',
        'stage', 'group_name', 'status', 'venue',
        'home_score', 'away_score', 'home_score_ht', 'away_score_ht',
    ];

    protected $casts = [
        'match_date' => 'datetime',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class);
    }

    public function accuracy(): HasOne
    {
        return $this->hasOne(PredictionAccuracy::class);
    }
}
