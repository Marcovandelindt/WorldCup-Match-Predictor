<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamRecentMatch extends Model
{
    protected $fillable = [
        'team_id', 'opponent_api_id', 'opponent_name',
        'match_date', 'goals_scored', 'goals_conceded',
        'result', 'competition',
    ];

    protected $casts = [
        'match_date' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
