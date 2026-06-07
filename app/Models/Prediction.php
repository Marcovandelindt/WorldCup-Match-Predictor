<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;


class Prediction extends Model
{
    protected $fillable = [
        'match_id', 'predicted_home', 'predicted_away', 'confidence_pct',
        'lambda_home', 'lambda_away', 'weight_form', 'weight_h2h',
        'weight_fifa', 'weight_wc_history', 'top_scorelines', 'generated_at',
    ];

    protected $casts = [
        'top_scorelines' => 'array',
        'generated_at'   => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function accuracy(): HasOne
    {
        return $this->hasOne(PredictionAccuracy::class);
    }
}
