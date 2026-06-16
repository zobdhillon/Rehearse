<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Scenario;
use App\Models\UserStat;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user   = auth()->user();
        $userId = $user->id;

        // ── Cached stats (1 hour TTL, invalidated on session complete/start) ──
        $cached = Cache::remember("dashboard.{$userId}", 3600, function () use ($userId) {

            // ── Single query: all completed conversations with scores ──────────
            $allCompleted = Conversation::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereNotNull('scores')
                ->get(['scores', 'created_at', 'scenario_id']);

            $parsedScores = $allCompleted->map(
                fn($c) => is_array($c->scores) ? $c->scores : json_decode($c->scores, true)
            )->filter();

            // ── Single query: all conversations (any status) ──────────────────
            $allConversations = Conversation::where('user_id', $userId)
                ->get(['id', 'scenario_id', 'status', 'created_at', 'scores']);

            // Core counts — derived from collections, no extra queries
            $totalSessions     = $allConversations->count();
            $completedSessions = $allConversations->where('status', 'completed')->count();
            $scenariosTried    = $allConversations->pluck('scenario_id')->unique()->count();

            // Best score
            $bestScore = $parsedScores->map(fn($s) => $s['final'] ?? null)->filter()->max();

            // ── Streak — one query instead of one-per-day loop ────────────────
            $sessionDates = $allConversations
                ->pluck('created_at')
                ->map(fn($d) => $d->toDateString())
                ->unique()
                ->sort()
                ->values();

            $currentStreak = 0;
            $checkDate     = now()->toDateString();

            foreach ($sessionDates->reverse() as $date) {
                if ($date === $checkDate) {
                    $currentStreak++;
                    $checkDate = now()->subDays($currentStreak)->toDateString();
                } elseif ($date < $checkDate) {
                    break;
                }
            }

            // ── Weekly activity — derived from collection ─────────────────────
            $weekStart      = now()->startOfWeek(0);
            $weeklyActivity = [];

            for ($i = 0; $i < 7; $i++) {
                $day              = $weekStart->copy()->addDays($i)->toDateString();
                $weeklyActivity[] = $allConversations
                    ->filter(fn($c) => $c->created_at->toDateString() === $day)
                    ->count();
            }

            $completedThisWeek = $allConversations
                ->where('status', 'completed')
                ->filter(fn($c) => $c->created_at->between(
                    now()->startOfWeek(0),
                    now()->endOfWeek(6)
                ))
                ->count();

            // ── Skill averages ────────────────────────────────────────────────
            $skillKeys = ['clarity', 'confidence', 'objective', 'adaptability'];
            $skillAvgs = array_fill_keys($skillKeys, 0);

            if ($parsedScores->count() > 0) {
                foreach ($skillKeys as $skill) {
                    $skillAvgs[$skill] = (int) round(
                        $parsedScores->avg(fn($s) => $s[$skill] ?? 0)
                    );
                }
            }

            // ── Score improvement (this month vs last) ────────────────────────
            $thisMonth = now()->month;
            $thisYear  = now()->year;
            $lastMonth = now()->subMonth()->month;
            $lastYear  = now()->subMonth()->year;

            $thisMonthScores = $parsedScores->filter(function ($s, $key) use ($allCompleted, $thisMonth, $thisYear) {
                $c = $allCompleted->values()->get($key);
                return $c && $c->created_at->month === $thisMonth && $c->created_at->year === $thisYear;
            });

            $lastMonthScores = $parsedScores->filter(function ($s, $key) use ($allCompleted, $lastMonth, $lastYear) {
                $c = $allCompleted->values()->get($key);
                return $c && $c->created_at->month === $lastMonth && $c->created_at->year === $lastYear;
            });

            $avgThisMonth    = $thisMonthScores->avg(fn($s) => $s['final'] ?? 0) ?? 0;
            $avgLastMonth    = $lastMonthScores->avg(fn($s) => $s['final'] ?? 0) ?? 0;
            $scoreImprovement = (int) round($avgThisMonth - $avgLastMonth);

            $skillDeltas = [];
            foreach ($skillKeys as $skill) {
                $lastAvg           = $lastMonthScores->count() > 0
                    ? $lastMonthScores->avg(fn($s) => $s[$skill] ?? 0)
                    : null;
                $skillDeltas[$skill] = $lastAvg !== null
                    ? (int) round($skillAvgs[$skill] - $lastAvg)
                    : 0;
            }

            return compact(
                'totalSessions',
                'completedSessions',
                'scenariosTried',
                'bestScore',
                'currentStreak',
                'weeklyActivity',
                'completedThisWeek',
                'scoreImprovement',
                'skillAvgs',
                'skillDeltas'
            );
        });

        // ── Not cached: streak badge update + recent sessions ─────────────────
        // UserStat update kept outside cache — it's a write, not a read
        $userStat = UserStat::firstOrCreate(['user_id' => $userId]);
        if ($cached['currentStreak'] > $userStat->best_streak) {
            $userStat->update(['best_streak' => $cached['currentStreak']]);
        }

        // Recent sessions kept outside cache — always fresh, lightweight query
        // No N+1: eager loads scenario in a single JOIN
        $recentSessions = Conversation::where('user_id', $userId)
            ->with('scenario:id,title,color')
            ->latest()
            ->take(5)
            ->get(['id', 'scenario_id', 'created_at', 'status', 'scores'])
            ->map(function ($c) {
                $scores = is_array($c->scores)
                    ? $c->scores
                    : ($c->scores ? json_decode($c->scores, true) : null);

                return [
                    'id'           => $c->id,
                    'created_at'   => $c->created_at,
                    'is_completed' => $c->status === 'completed',
                    'score'        => $scores['final'] ?? null,
                    'scenario'     => $c->scenario ? [
                        'id'    => $c->scenario->id,
                        'title' => $c->scenario->title,
                        'color' => $c->scenario->color ?? 'purple',
                    ] : null,
                ];
            });

        // Featured scenarios — static, cached separately for 24 hours
        $featuredScenarios = Cache::remember(
            'dashboard.featuredScenarios',
            86400,
            fn() =>
            Scenario::select('id', 'title', 'description')->take(3)->get()
        );

        return Inertia::render('Dashboard', [
            'stats' => [
                'userName'          => $user->name,
                'totalSessions'     => $cached['totalSessions'],
                'completedSessions' => $cached['completedSessions'],
                'scenariosTried'    => $cached['scenariosTried'],
                'bestScore'         => $cached['bestScore'],
                'currentStreak'     => $cached['currentStreak'],
                'bestStreak'        => $userStat->best_streak,
                'thisWeek'          => array_sum($cached['weeklyActivity']),
                'completedThisWeek' => $cached['completedThisWeek'],
                'scoreImprovement'  => $cached['scoreImprovement'],
                'weeklyActivity'    => $cached['weeklyActivity'],
                'skillAvgs'         => $cached['skillAvgs'],
                'skillDeltas'       => $cached['skillDeltas'],
            ],
            'recentSessions'    => $recentSessions,
            'featuredScenarios' => $featuredScenarios,
        ]);
    }
}
