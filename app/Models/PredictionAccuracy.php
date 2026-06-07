<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionAccuracy extends Model
{
    protected $fillable = [
        'prediction_id', 'match_id',
        'predicted_home', 'predicted_away',
        'actual_home', 'actual_away',
        'exact_score', 'correct_winner', 'correct_goal_diff',
        'points_earned', 'evaluated_at',
    ];

    protected $casts = [
        'exact_score'       => 'boolean',
        'correct_winner'    => 'boolean',
        'correct_goal_diff' => 'boolean',
        'evaluated_at'      => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
