<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamRecentMatch;

class TeamController extends Controller
{
    public function show(Team $team)
    {
        $recentMatches = TeamRecentMatch::where('team_id', $team->id)
            ->orderByDesc('match_date')
            ->limit(10)
            ->get();

        $wcMatches = TeamRecentMatch::where('team_id', $team->id)
            ->where('competition', 'FIFA World Cup')
            ->orderByDesc('match_date')
            ->get()
            ->groupBy(fn ($m) => substr($m->match_date, 0, 4))
            ->sortKeysDesc();

        // Keyed map api_id → Team for flag lookup in recent matches
        $teamsByApiId = Team::all()->keyBy('api_id');

        // Last 5 for form pills
        $form = $recentMatches->take(5);

        // WK 2026 matches for this team, grouped by stage
        $wkMatches = FootballMatch::where('home_team_id', $team->id)
            ->orWhere('away_team_id', $team->id)
            ->with(['homeTeam', 'awayTeam', 'prediction', 'accuracy'])
            ->orderBy('match_date')
            ->get();

        $wkMatchesByStage = $wkMatches->groupBy('stage');

        // Group name from first GROUP match
        $groupMatch = $wkMatches->firstWhere('stage', 'GROUP');
        $groupName  = $groupMatch?->group_name
            ? str_replace('GROUP_', 'Groep ', $groupMatch->group_name)
            : null;

        // Stats from recent matches
        $goalsScored   = $recentMatches->sum('goals_scored');
        $goalsConceded = $recentMatches->sum('goals_conceded');

        $stageLabels = [
            'GROUP' => 'Groepsfase',
            'R16'   => 'Achtste finale',
            'QF'    => 'Kwartfinale',
            'SF'    => 'Halve finale',
            'THIRD' => 'Derde plaats',
            'FINAL' => 'Finale',
        ];

        return view('teams.show', compact(
            'team', 'recentMatches', 'teamsByApiId', 'form',
            'wkMatchesByStage', 'groupName', 'goalsScored', 'goalsConceded',
            'stageLabels', 'wcMatches',
        ));
    }
}
