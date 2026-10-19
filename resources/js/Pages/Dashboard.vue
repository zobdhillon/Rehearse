<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalSessions: 0,
            completedSessions: 0,
            bestScore: null,
            currentStreak: 0,
            weeklyActivity: [0, 0, 0, 0, 0, 0, 0],
            skillAvgs: {
                clarity: 0,
                confidence: 0,
                objective: 0,
                adaptability: 0,
            },
            skillDeltas: {
                clarity: 0,
                confidence: 0,
                objective: 0,
                adaptability: 0,
            },
        }),
    },
    recentSessions: { type: Array, default: () => [] },
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return "Good morning";
    if (h < 17) return "Good afternoon";
    return "Good evening";
});

function plural(n, one, many = one + "s") {
    return `${n} ${n === 1 ? one : many}`;
}

const improvement = computed(() => {
    const v = Number(props.stats.scoreImprovement ?? 0);
    if (!v) return null;
    return {
        label: `${v > 0 ? "+" : "−"}${Math.abs(v)} this month`,
        positive: v > 0,
    };
});

const unfinished = computed(
    () => props.recentSessions.find((s) => !s.is_completed) ?? null,
);

const averageSkillScore = computed(() => {
    const values = skillsWithMeta.value
        .filter((s) => !s.isPending)
        .map((s) => s.value);
    if (values.length === 0) return 0;
    return Math.round(values.reduce((a, b) => a + b, 0) / values.length);
});

const userName = computed(() => {
    const name = props.stats.userName ?? "there";
    return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
});

function formatDate(d) {
    const date = new Date(d);
    const diff = Math.floor((new Date() - date) / 86400000);
    if (diff <= 0) return "Today";
    if (diff === 1) return "Yesterday";
    if (diff < 7) return `${diff}d ago`;
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

// ── Weekly activity ───────────────────────────────────────
const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const todayIdx = new Date().getDay();
const DOT_ROWS = 7;

const activityHistory = computed(
    () => props.stats.weeklyActivity ?? [0, 0, 0, 0, 0, 0, 0],
);

function filledDots(value) {
    if (value <= 0) return 0;
    return Math.min(value, DOT_ROWS);
}

// ── Level / progress ──────────────────────────────────────
const currentLevel = computed(() => {
    const s = props.stats.completedSessions;
    if (s >= 20) return { label: "Level 4", title: "Advanced Communicator" };
    if (s >= 10)
        return { label: "Level 3", title: "Intermediate Communicator" };
    if (s >= 5) return { label: "Level 2", title: "Developing Speaker" };
    return { label: "Level 1", title: "Beginner" };
});

const levelProgress = computed(() => {
    const s = props.stats.completedSessions ?? 0;
    if (s >= 20) return 1;
    if (s >= 10) return (s - 10) / 10;
    if (s >= 5) return (s - 5) / 5;
    return s / 5;
});

// ── Skills ────────────────────────────────────────────────
const skills = [
    { key: "clarity", label: "Clarity" },
    { key: "confidence", label: "Confidence" },
    { key: "objective", label: "Objective" },
    { key: "adaptability", label: "Adaptability" },
];

const skillsWithMeta = computed(() => {
    const avgs = props.stats.skillAvgs ?? {};
    const deltas = props.stats.skillDeltas ?? {};
    const hasData = (props.stats.completedSessions ?? 0) > 0;

    return skills.map((skill) => {
        const value = hasData ? (avgs[skill.key] ?? 0) : 0;
        const delta = hasData ? (deltas[skill.key] ?? 0) : 0;

        return {
            ...skill,
            value,
            delta,
            isPending: !hasData,
        };
    });
});

function chartCoords(points, w, h, yMin = 0, yMax = 100) {
    if (!points?.length) return [];
    const range = yMax - yMin || 1;
    const innerH = h - 8;
    const step = points.length > 1 ? w / (points.length - 1) : 0;

    return points.map((v, i) => ({
        x: +(points.length > 1 ? i * step : w / 2).toFixed(1),
        y: +(h - 4 - ((v - yMin) / range) * innerH).toFixed(1),
    }));
}

function linePath(points, w, h, yMin = 0, yMax = 100) {
    const coords = chartCoords(points, w, h, yMin, yMax);
    if (!coords.length) return "";
    if (coords.length === 1) {
        const y = coords[0].y;
        return `M 0 ${y} L ${w} ${y}`;
    }
    return `M ${coords.map((c) => `${c.x} ${c.y}`).join(" L ")}`;
}

function areaPath(points, w, h, yMin = 0, yMax = 100) {
    const coords = chartCoords(points, w, h, yMin, yMax);
    if (!coords.length) return "";
    if (coords.length === 1) {
        const y = coords[0].y;
        return `M 0 ${h} L 0 ${y} L ${w} ${y} L ${w} ${h} Z`;
    }
    const line = coords.map((c) => `${c.x} ${c.y}`).join(" L ");
    return `M ${line} L ${w} ${h} L 0 ${h} Z`;
}

function lastPoint(points, w, h, yMin = 0, yMax = 100) {
    const coords = chartCoords(points, w, h, yMin, yMax);
    if (!coords.length) return { x: w / 2, y: h / 2 };
    return coords[coords.length - 1];
}

const scoreHistory = computed(() =>
    [...props.recentSessions]
        .reverse()
        .map((s) => s.score)
        .filter((s) => s !== null && s !== undefined),
);

const hasBestScore = computed(
    () => props.stats.bestScore !== null && props.stats.bestScore !== undefined,
);

const hasScoreChart = computed(() => scoreHistory.value.length > 0);

const sparkEndpoint = computed(() => lastPoint(scoreHistory.value, 240, 64));

// ── Radar chart ───────────────────────────────────────────
const RADAR_CX = 100;
const RADAR_CY = 100;
const RADAR_MAX_R = 72;
const RADAR_ANGLES = [-90, 0, 90, 180];

function radarPoint(value, angleDeg, cx, cy, maxR) {
    const r = (Math.max(0, Math.min(value, 100)) / 100) * maxR;
    const rad = (angleDeg * Math.PI) / 180;
    return {
        x: +(cx + r * Math.cos(rad)).toFixed(1),
        y: +(cy + r * Math.sin(rad)).toFixed(1),
    };
}

const radarSkills = computed(() =>
    skillsWithMeta.value.map((skill, i) => ({
        ...skill,
        axis: radarPoint(100, RADAR_ANGLES[i], RADAR_CX, RADAR_CY, RADAR_MAX_R),
        point: radarPoint(
            skill.isPending ? 0 : skill.value,
            RADAR_ANGLES[i],
            RADAR_CX,
            RADAR_CY,
            RADAR_MAX_R,
        ),
        labelPos: radarPoint(
            118,
            RADAR_ANGLES[i],
            RADAR_CX,
            RADAR_CY,
            RADAR_MAX_R,
        ),
    })),
);

const radarPolygon = computed(() =>
    radarSkills.value.map((s) => `${s.point.x},${s.point.y}`).join(" "),
);

const radarGridRings = [25, 50, 75, 100];

const radarRings = computed(() =>
    radarGridRings.map((ring) =>
        RADAR_ANGLES.map((a) => {
            const p = radarPoint(ring, a, RADAR_CX, RADAR_CY, RADAR_MAX_R);
            return `${p.x},${p.y}`;
        }).join(" "),
    ),
);

function skillAxisLabel(key) {
    return (
        {
            clarity: "Clarity",
            confidence: "Conf.",
            objective: "Object.",
            adaptability: "Adapt.",
        }[key] ?? key
    );
}

// ── New user state ────────────────────────────────────────
const isNewUser = computed(() => (props.stats.completedSessions ?? 0) === 0);

const lowestSkill = computed(() => {
    if (isNewUser.value) return null;
    const rated = skillsWithMeta.value.filter((s) => !s.isPending);
    if (!rated.length) return null;
    return rated.reduce((a, b) => (b.value < a.value ? b : a));
});
function scoreColor(score) {
    if (score === null || score === undefined)
        return { text: "var(--text-3)", bg: "var(--border)" };
    if (score >= 80) return { text: "var(--green)", bg: "var(--green-bg)" };
    if (score >= 50) return { text: "var(--amber)", bg: "var(--amber-bg)" };
    if (score >= 0) return { text: "var(--red)", bg: "var(--red-bg)" };
    return { text: "var(--text-3)", bg: "var(--bg-surface2)" };
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #title>Dashboard</template>

        <!-- Outer wrapper: fills the available height from AuthenticatedLayout, no scroll -->
        <div
            class="h-full overflow-y-auto lg:overflow-hidden p-3 sm:p-4 md:p-5 pb-6 sm:pb-4"
        >
            <div
                class="h-full flex flex-col lg:flex-row gap-3 sm:gap-4 lg:gap-5 min-h-0"
            >
                <!-- ── LEFT COLUMN ──────────────────────────────── -->
                <div
                    class="flex flex-col gap-4 w-full lg:w-[62%] lg:flex-shrink-0 lg:min-h-0 lg:overflow-hidden"
                >
                    <!-- BANNER CARD — tighter padding, no getting started bar -->
                    <div
                        class="rounded-2xl overflow-hidden relative flex-shrink-0"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <!-- Decorative orbs -->
                        <div
                            style="
                                position: absolute;
                                inset: 0;
                                overflow: hidden;
                                pointer-events: none;
                            "
                        >
                            <div
                                style="
                                    position: absolute;
                                    right: -24px;
                                    top: -32px;
                                    width: 180px;
                                    height: 180px;
                                    border-radius: 50%;
                                    background: var(--accent-2);
                                    opacity: 0.12;
                                "
                            />
                            <div
                                style="
                                    position: absolute;
                                    right: 80px;
                                    bottom: -20px;
                                    width: 110px;
                                    height: 110px;
                                    border-radius: 50%;
                                    background: var(--accent-2);
                                    opacity: 0.08;
                                "
                            />
                            <div
                                style="
                                    position: absolute;
                                    right: 200px;
                                    top: 10px;
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    background: var(--accent-2);
                                    opacity: 0.05;
                                "
                            />
                        </div>

                        <!-- Content — tighter padding -->
                        <div
                            class="relative z-10 flex flex-col justify-between px-5 py-4 sm:px-7"
                        >
                            <!-- Top row -->
                            <div>
                                <p
                                    class="mb-0.5 text-[11px] font-medium uppercase tracking-widest"
                                    style="color: var(--text-3)"
                                >
                                    {{ greeting }}, {{ userName }}
                                </p>
                                <h1
                                    class="text-xl font-bold leading-tight tracking-tight sm:text-[1.6rem]"
                                    style="color: var(--text)"
                                >
                                    {{
                                        stats.completedSessions > 0
                                            ? `${plural(stats.completedSessions, "session")} completed.`
                                            : "Ready to practice?"
                                    }}
                                </h1>
                                <p
                                    class="mt-0.5 text-[12px]"
                                    style="color: var(--text-3)"
                                >
                                    <template v-if="stats.bestScore">
                                        Best score {{ stats.bestScore }}/100
                                    </template>
                                    <template v-else>
                                        Pick a scenario below to start your
                                        first session.
                                    </template>
                                </p>
                            </div>

                            <!-- Bottom row -->
                            <div
                                class="mt-3 flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                            >
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:flex-wrap"
                                >
                                    <Link
                                        :href="
                                            unfinished
                                                ? route(
                                                      'conversations.show',
                                                      unfinished.id,
                                                  )
                                                : route('scenarios.index')
                                        "
                                        class="btn-primary justify-center px-4 py-2 text-center text-[12px]"
                                    >
                                        {{
                                            unfinished
                                                ? "Resume session"
                                                : "Start Practice"
                                        }}
                                        <svg
                                            width="11"
                                            height="11"
                                            viewBox="0 0 12 12"
                                            fill="none"
                                        >
                                            <path
                                                d="M2 6h8M6 2l4 4-4 4"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </Link>
                                    <Link
                                        :href="route('scenarios.index')"
                                        class="rounded-lg border px-4 py-2 text-center text-[12px] font-medium transition-colors hover:bg-[var(--bg-surface2)]"
                                        style="
                                            color: var(--text-2);
                                            border-color: var(--border-strong);
                                        "
                                    >
                                        Browse Scenarios
                                    </Link>
                                </div>

                                <!-- Level badge -->
                                <div
                                    class="w-full border-t pt-3 md:w-auto md:border-t-0 md:pt-0 md:text-right"
                                    style="border-color: var(--border)"
                                >
                                    <div
                                        class="text-[11px] font-semibold"
                                        style="color: var(--text-2)"
                                    >
                                        {{ currentLevel.label }}
                                        <span
                                            class="font-normal"
                                            style="color: var(--text-3)"
                                        >
                                            · {{ currentLevel.title }}</span
                                        >
                                    </div>
                                    <div
                                        class="mt-1 h-[3px] w-full max-w-[8rem] rounded-full overflow-hidden md:ml-auto"
                                        style="background: var(--track-bg)"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :style="{
                                                width:
                                                    levelProgress * 100 + '%',
                                                background:
                                                    'var(--gradient-primary)',
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="mt-0.5 text-[10px]"
                                        style="color: var(--text-3)"
                                    >
                                        {{ Math.round(levelProgress * 100) }}%
                                        to next level
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Getting Started bar REMOVED -->
                    </div>

                    <!-- TWO STAT CARDS -->
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-shrink-0"
                    >
                        <!-- Score trend card -->
                        <div
                            class="rounded-2xl p-4"
                            style="
                                background: var(--bg-surface);
                                border: 1px solid var(--border);
                                box-shadow: var(--shadow-md);
                            "
                        >
                            <div
                                class="mb-1 flex items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="text-[11px] font-medium uppercase tracking-wider"
                                        style="color: var(--text-3)"
                                    >
                                        Best Score
                                    </div>
                                    <div
                                        v-if="hasBestScore"
                                        class="mt-1 text-[2rem] font-bold leading-none tracking-tight tabular-nums"
                                        style="color: var(--text)"
                                    >
                                        {{ stats.bestScore
                                        }}<span
                                            class="text-[1rem] font-medium"
                                            style="color: var(--text-3)"
                                            >/100</span
                                        >
                                    </div>
                                    <div v-else class="mt-1">
                                        <div
                                            class="text-[14px] font-semibold leading-tight"
                                            style="color: var(--text-2)"
                                        >
                                            Not scored yet
                                        </div>
                                        <div
                                            class="mt-0.5 text-[10px]"
                                            style="color: var(--text-3)"
                                        >
                                            Complete a session to track progress
                                        </div>
                                    </div>
                                </div>
                                <span
                                    v-if="improvement"
                                    class="flex-shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold"
                                    :style="
                                        improvement.positive
                                            ? 'background: var(--green-bg); color: var(--green)'
                                            : 'background: var(--red-bg); color: var(--red)'
                                    "
                                >
                                    {{ improvement.label }}
                                </span>
                            </div>

                            <div
                                class="text-[11px] mb-2"
                                style="color: var(--text-3)"
                            >
                                Last 10 sessions
                            </div>

                            <!-- Chart with Y-axis -->
                            <div
                                v-if="hasScoreChart"
                                class="flex items-stretch gap-2"
                            >
                                <!-- Y-axis labels: 100 / 50 / 0 only -->
                                <div
                                    class="flex flex-col justify-between flex-shrink-0"
                                    style="height: 56px"
                                >
                                    <span
                                        class="10px] font-medium leading-none tabular-nums"
                                        style="color: var(--text-3)"
                                        >100</span
                                    >
                                    <span
                                        class="text-[10px] font-medium leading-none tabular-nums"
                                        style="color: var(--text-3)"
                                        >50</span
                                    >
                                    <span
                                        class="text-[10px] font-medium leading-none tabular-nums"
                                        style="color: var(--text-3)"
                                        >0</span
                                    >
                                </div>

                                <!-- SVG chart — no value labels, just the line -->
                                <svg
                                    class="flex-1 block"
                                    height="56"
                                    viewBox="0 0 220 56"
                                    preserveAspectRatio="none"
                                >
                                    <defs>
                                        <linearGradient
                                            id="scoreGrad"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="var(--accent-2)"
                                                stop-opacity="0.28"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="var(--accent)"
                                                stop-opacity="0"
                                            />
                                        </linearGradient>
                                    </defs>
                                    <!-- Subtle gridlines at 100, 50, 0 -->
                                    <line
                                        x1="0"
                                        y1="2"
                                        x2="220"
                                        y2="2"
                                        stroke="var(--border)"
                                        stroke-width="0.5"
                                    />
                                    <line
                                        x1="0"
                                        y1="28"
                                        x2="220"
                                        y2="28"
                                        stroke="var(--border)"
                                        stroke-width="0.5"
                                    />
                                    <line
                                        x1="0"
                                        y1="54"
                                        x2="220"
                                        y2="54"
                                        stroke="var(--border)"
                                        stroke-width="0.5"
                                    />
                                    <!-- Area fill -->
                                    <path
                                        :d="areaPath(scoreHistory, 220, 56)"
                                        fill="url(#scoreGrad)"
                                    />
                                    <!-- Line -->
                                    <path
                                        :d="linePath(scoreHistory, 220, 56)"
                                        fill="none"
                                        stroke="var(--accent)"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <!-- End dot only -->
                                    <circle
                                        :cx="lastPoint(scoreHistory, 220, 56).x"
                                        :cy="lastPoint(scoreHistory, 220, 56).y"
                                        r="3.5"
                                        fill="var(--bg-surface)"
                                        stroke="var(--accent)"
                                        stroke-width="2"
                                    />
                                </svg>
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else
                                class="flex h-14 flex-col items-center justify-center text-center px-4 rounded-xl"
                                style="
                                    background: var(--bg-surface2);
                                    border: 1px dashed var(--border-strong);
                                "
                            >
                                <span
                                    class="text-[11px] font-bold"
                                    style="color: var(--text-2)"
                                    >No sessions scored yet</span
                                >
                                <span
                                    class="text-[9px] mt-0.5"
                                    style="color: var(--text-3)"
                                    >Finish a session to track your progress
                                    here</span
                                >
                            </div>
                        </div>

                        <!-- Weekly activity card -->
                        <div
                            class="rounded-2xl p-4"
                            style="
                                background: var(--bg-surface);
                                border: 1px solid var(--border);
                                box-shadow: var(--shadow-md);
                            "
                        >
                            <div class="flex items-start justify-between mb-1">
                                <div>
                                    <div
                                        class="text-[11px] font-medium uppercase tracking-wider"
                                        style="color: var(--text-3)"
                                    >
                                        This Week
                                    </div>
                                    <div
                                        class="text-[2rem] font-bold leading-none tracking-tight tabular-nums mt-1"
                                        style="color: var(--text)"
                                    >
                                        {{
                                            activityHistory.reduce(
                                                (a, b) => a + b,
                                                0,
                                            )
                                        }}
                                        <span
                                            class="text-[1rem] font-medium"
                                            style="color: var(--text-3)"
                                            >sessions</span
                                        >
                                    </div>
                                </div>
                                <span
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded"
                                    style="
                                        background: var(--accent-bg);
                                        color: var(--accent);
                                    "
                                >
                                    {{ stats.completedThisWeek ?? 0 }} completed
                                </span>
                            </div>
                            <div
                                class="text-[11px] mb-2"
                                style="color: var(--text-3)"
                            >
                                Daily sessions Sun – Sat
                            </div>

                            <div
                                class="flex items-stretch justify-between gap-1.5"
                                style="height: 56px"
                            >
                                <div
                                    v-for="(count, idx) in activityHistory"
                                    :key="idx"
                                    class="flex flex-1 flex-col items-center"
                                >
                                    <div
                                        class="flex flex-1 w-full flex-col-reverse items-center justify-between py-0.5"
                                    >
                                        <span
                                            v-for="row in DOT_ROWS"
                                            :key="row"
                                            class="rounded-[2.5px] transition-colors duration-300"
                                            style="width: 8px"
                                            :style="{
                                                height:
                                                    count <= 0 && row === 1
                                                        ? '4px'
                                                        : '6px',
                                                background:
                                                    row <= filledDots(count)
                                                        ? idx === todayIdx
                                                            ? 'var(--accent)'
                                                            : 'var(--accent-2)'
                                                        : count <= 0 &&
                                                            row === 1
                                                          ? 'var(--accent-bg)'
                                                          : 'var(--track-bg)',
                                                opacity:
                                                    row <= filledDots(count)
                                                        ? idx === todayIdx
                                                            ? 1
                                                            : 0.55
                                                        : count <= 0 &&
                                                            row === 1
                                                          ? 0.6
                                                          : 1,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="mt-1 w-full text-center text-[9px] font-semibold leading-none"
                                        :style="
                                            idx === todayIdx
                                                ? 'color:var(--accent)'
                                                : 'color:var(--text-3)'
                                        "
                                        >{{ weekDays[idx][0] }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- YOUR SKILLS CARD — fills remaining height -->
                    <div
                        class="flex flex-col rounded-2xl p-4 min-h-0 flex-1 lg:overflow-hidden"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <div
                            class="mb-3 flex flex-shrink-0 items-center justify-between"
                        >
                            <div>
                                <div
                                    class="text-[13px] font-semibold"
                                    style="color: var(--text)"
                                >
                                    Your Skills
                                </div>
                                <div
                                    class="text-[11px]"
                                    style="color: var(--text-3)"
                                >
                                    Average across completed sessions
                                </div>
                            </div>
                            <Link
                                :href="route('conversations.index')"
                                class="text-[11px] font-medium hover:opacity-70 transition-all hover:underline underline-offset-2"
                                style="color: var(--accent)"
                            >
                                View details →
                            </Link>
                        </div>

                        <!-- New user: single focused CTA instead of 4 "Pending" badges -->
                        <div
                            v-if="isNewUser"
                            class="flex flex-1 items-center justify-center"
                        >
                            <div
                                class="flex flex-col items-center gap-3 text-center max-w-xs"
                            >
                                <div
                                    class="h-10 w-10 rounded-xl flex items-center justify-center"
                                    style="background: var(--accent-bg)"
                                >
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="var(--accent)"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <polygon
                                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-[13px] font-semibold"
                                        style="color: var(--text-2)"
                                    >
                                        Complete a session to unlock skills
                                    </p>
                                    <p
                                        class="mt-1 text-[11px]"
                                        style="color: var(--text-3)"
                                    >
                                        Your clarity, confidence, and
                                        adaptability scores will show here after
                                        your first practice.
                                    </p>
                                </div>
                                <Link
                                    :href="route('scenarios.index')"
                                    class="btn-primary text-[11px] px-4 py-2"
                                >
                                    Start Practice →
                                </Link>
                            </div>
                        </div>

                        <!-- Has data: radar + skill badges -->
                        <!-- Has data: skills + radar + focus tip -->
                        <div v-else class="flex min-h-0 flex-1 flex-col gap-3">
                            <div
                                class="grid min-h-0 flex-1 gap-4 md:grid-cols-[1fr_auto] md:items-center"
                            >
                                <div
                                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="skill in skillsWithMeta"
                                        :key="skill.key"
                                        class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                                        style="
                                            background: var(--bg-surface2);
                                            border: 1px solid var(--border);
                                        "
                                    >
                                        <div
                                            class="h-8 w-1.5 flex-shrink-0 rounded-full"
                                            :style="{
                                                background: skill.isPending
                                                    ? 'var(--track-bg)'
                                                    : 'var(--gradient-primary)',
                                            }"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="text-[11px] font-medium leading-tight"
                                                style="color: var(--text-2)"
                                            >
                                                {{ skill.label }}
                                            </div>
                                            <div
                                                class="mt-0.5 flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-[13px] font-bold tabular-nums leading-none"
                                                    :style="
                                                        skill.isPending
                                                            ? 'color: var(--text-3)'
                                                            : 'color: var(--text)'
                                                    "
                                                >
                                                    {{
                                                        skill.isPending
                                                            ? "—"
                                                            : `${skill.value}%`
                                                    }}
                                                </span>
                                                <span
                                                    v-if="skill.isPending"
                                                    class="text-[10px]"
                                                    style="color: var(--text-3)"
                                                    >Pending</span
                                                >
                                                <span
                                                    v-else-if="
                                                        skill.delta !== 0
                                                    "
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :style="
                                                        skill.delta > 0
                                                            ? 'background: var(--green-bg); color: var(--green)'
                                                            : 'background: var(--red-bg); color: var(--red)'
                                                    "
                                                >
                                                    {{
                                                        skill.delta > 0
                                                            ? "+"
                                                            : "−"
                                                    }}{{
                                                        Math.abs(skill.delta)
                                                    }}%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col items-center">
                                    <svg
                                        viewBox="-12 -12 224 224"
                                        class="h-[190px] w-[190px]"
                                        role="img"
                                        aria-label="Skill balance chart"
                                    >
                                        <polygon
                                            v-for="(points, i) in radarRings"
                                            :key="i"
                                            :points="points"
                                            fill="none"
                                            stroke="var(--border-strong)"
                                            stroke-width="0.75"
                                        />
                                        <line
                                            v-for="s in radarSkills"
                                            :key="s.key + '-axis'"
                                            :x1="RADAR_CX"
                                            :y1="RADAR_CY"
                                            :x2="s.axis.x"
                                            :y2="s.axis.y"
                                            stroke="var(--border-strong)"
                                            stroke-width="0.75"
                                        />
                                        <polygon
                                            :points="radarPolygon"
                                            fill="var(--accent)"
                                            fill-opacity="0.22"
                                            stroke="var(--accent)"
                                            stroke-width="2"
                                            stroke-linejoin="round"
                                        />
                                        <circle
                                            v-for="s in radarSkills"
                                            :key="s.key + '-dot'"
                                            :cx="s.point.x"
                                            :cy="s.point.y"
                                            r="3"
                                            fill="var(--accent)"
                                        />
                                        <text
                                            v-for="s in radarSkills"
                                            :key="s.key + '-label'"
                                            :x="s.labelPos.x"
                                            :y="s.labelPos.y"
                                            text-anchor="middle"
                                            dominant-baseline="middle"
                                            font-size="10"
                                            font-weight="600"
                                            fill="var(--text-3)"
                                        >
                                            {{ skillAxisLabel(s.key) }}
                                        </text>
                                    </svg>
                                    <div
                                        class="mt-1 text-[11px]"
                                        style="color: var(--text-3)"
                                    >
                                        Average
                                        <span
                                            class="font-bold"
                                            style="color: var(--text)"
                                            >{{ averageSkillScore }}%</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="lowestSkill"
                                class="flex flex-shrink-0 flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3"
                                style="
                                    background: var(--accent-bg);
                                    border: 1px solid var(--border);
                                "
                            >
                                <p
                                    class="text-[12px]"
                                    style="color: var(--text-2)"
                                >
                                    Focus next:
                                    <strong style="color: var(--text)">{{
                                        lowestSkill.label
                                    }}</strong>
                                    is your lowest skill ({{
                                        lowestSkill.value
                                    }}%). Practice a scenario to improve it.
                                </p>
                                <Link
                                    :href="route('scenarios.index')"
                                    class="btn-primary px-3.5 py-1.5 text-[11px]"
                                >
                                    Practice now
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end LEFT COLUMN -->

                <!-- ── RIGHT COLUMN ─────────────────────────────── -->
                <div
                    class="flex min-w-0 flex-1 flex-col gap-4 lg:h-full lg:min-h-0 lg:overflow-hidden"
                >
                    <!-- RECENT SESSIONS — grows to fill -->
                    <div
                        class="flex flex-col overflow-hidden rounded-2xl min-h-0 flex-1"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <div
                            class="flex items-center justify-between px-5 py-3 border-b flex-shrink-0"
                            style="border-color: var(--border)"
                        >
                            <div>
                                <div
                                    class="text-[13px] font-semibold"
                                    style="color: var(--text)"
                                >
                                    Recent Sessions
                                </div>
                                <div
                                    class="text-[11px]"
                                    style="color: var(--text-3)"
                                >
                                    Latest practice activity
                                </div>
                            </div>
                            <Link
                                :href="route('conversations.index')"
                                class="text-[11px] font-medium hover:opacity-70 transition-opacity hover:underline underline-offset-2"
                                style="color: var(--accent)"
                                >View all →</Link
                            >
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <!-- Empty state -->
                            <div
                                v-if="recentSessions.length === 0"
                                class="flex h-full flex-col justify-between px-5 py-5"
                            >
                                <div>
                                    <div class="mb-4 text-center">
                                        <div
                                            class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-xl"
                                            style="background: var(--accent-bg)"
                                        >
                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="var(--accent)"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path
                                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                                />
                                            </svg>
                                        </div>
                                        <p
                                            class="text-[13px] font-medium"
                                            style="color: var(--text-2)"
                                        >
                                            No sessions yet
                                        </p>
                                        <p
                                            class="mt-1 text-[11px]"
                                            style="color: var(--text-3)"
                                        >
                                            Your practice history will appear
                                            here.
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-2.5">
                                        <div
                                            v-for="(row, i) in [
                                                { title: '64%', sub: '34%' },
                                                { title: '50%', sub: '28%' },
                                                { title: '38%', sub: '22%' },
                                            ]"
                                            :key="i"
                                            class="flex items-center gap-3 rounded-xl border border-dashed px-3 py-3"
                                            :style="{
                                                borderColor:
                                                    'var(--border-strong)',
                                                opacity: 1 - i * 0.22,
                                            }"
                                        >
                                            <div
                                                class="h-7 w-7 flex-shrink-0 rounded-lg"
                                                style="
                                                    background: var(--track-bg);
                                                "
                                            />
                                            <div
                                                class="flex min-w-0 flex-1 flex-col gap-1.5"
                                            >
                                                <div
                                                    class="h-2 rounded-full"
                                                    :style="{
                                                        background:
                                                            'var(--track-bg)',
                                                        width: row.title,
                                                    }"
                                                />
                                                <div
                                                    class="h-2 rounded-full"
                                                    :style="{
                                                        background:
                                                            'var(--track-bg)',
                                                        width: row.sub,
                                                    }"
                                                />
                                            </div>
                                            <div
                                                class="h-5 w-12 flex-shrink-0 rounded-md"
                                                style="
                                                    background: var(--track-bg);
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                                <Link
                                    :href="route('scenarios.index')"
                                    class="btn-primary mt-4 self-center text-[11px] px-4 py-2"
                                    >Browse Scenarios</Link
                                >
                            </div>

                            <!-- Session rows -->
                            <div v-else class="flex flex-col">
                                <Link
                                    v-for="session in recentSessions.slice(
                                        0,
                                        8,
                                    )"
                                    :key="session.id"
                                    :href="
                                        route('conversations.show', session.id)
                                    "
                                    class="flex items-center gap-3 px-5 py-3 border-b last:border-b-0 hover:bg-[var(--bg-surface2)] transition-colors"
                                    style="border-color: var(--border)"
                                >
                                    <div
                                        class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg"
                                        style="background: var(--accent-bg)"
                                    >
                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            style="color: var(--accent)"
                                        >
                                            <path
                                                d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-[12px] font-medium"
                                            style="color: var(--text)"
                                        >
                                            {{
                                                session.scenario?.title ??
                                                "Session"
                                            }}
                                        </div>
                                        <div
                                            class="text-[10px]"
                                            style="color: var(--text-3)"
                                        >
                                            {{ formatDate(session.created_at) }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-shrink-0 items-center gap-1.5"
                                    >
                                        <!-- Score badge: color-coded filled pill -->
                                        <span
                                            v-if="session.score !== null"
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums"
                                            :style="{
                                                color: scoreColor(session.score)
                                                    .text,
                                                background: scoreColor(
                                                    session.score,
                                                ).bg,
                                            }"
                                        >
                                            {{ session.score }}/100
                                        </span>

                                        <!-- Status badge: Resume = solid filled, Review = ghost outlined -->
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                                            :style="
                                                session.is_completed
                                                    ? 'color: var(--text-3); border: 1px solid var(--border-strong); background: transparent;'
                                                    : 'color: var(--accent); background: var(--accent-bg); border: 1px solid var(--accent);'
                                            "
                                        >
                                            {{
                                                session.is_completed
                                                    ? "Review"
                                                    : "Resume"
                                            }}
                                        </span>
                                    </div>
                                </Link>
                                <div
                                    v-if="recentSessions.length < 8"
                                    class="flex items-center justify-center p-5"
                                >
                                    <span
                                        class="text-[11px] font-medium"
                                        style="color: var(--text-3)"
                                        >Past sessions will appear here</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STREAK + STATS — fixed height at bottom -->
                    <div
                        class="flex-shrink-0 rounded-2xl p-4 mb-2 lg:mb-0"
                        style="
                            background: var(--gradient-primary);
                            box-shadow: 0 8px 24px
                                color-mix(
                                    in srgb,
                                    var(--accent) 30%,
                                    transparent
                                );
                            border: none;
                        "
                    >
                        <div class="grid grid-cols-3">
                            <div
                                class="pr-4"
                                style="
                                    border-right: 1px solid
                                        rgba(255, 255, 255, 0.2);
                                "
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-wider mb-1"
                                    style="color: rgba(255, 255, 255, 0.75)"
                                >
                                    Streak
                                </div>
                                <div
                                    class="text-[1.3rem] font-bold tabular-nums"
                                    style="color: #ffffff"
                                >
                                    {{
                                        stats.currentStreak > 0
                                            ? stats.currentStreak
                                            : (stats.bestStreak ?? 0)
                                    }}
                                    <span
                                        class="text-[11px] font-medium"
                                        style="color: rgba(255, 255, 255, 0.75)"
                                    >
                                        {{
                                            stats.currentStreak > 0
                                                ? "days"
                                                : "best"
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="text-[10px] font-medium"
                                    style="color: rgba(255, 255, 255, 0.65)"
                                >
                                    {{
                                        stats.currentStreak > 0
                                            ? "Active streak"
                                            : "No active streak"
                                    }}
                                </div>
                                <div
                                    class="text-[10px] font-medium"
                                    style="color: rgba(255, 255, 255, 0.65)"
                                >
                                    Best:
                                    {{
                                        stats.bestStreak ?? stats.currentStreak
                                    }}d
                                </div>
                            </div>

                            <div
                                class="px-4"
                                style="
                                    border-right: 1px solid
                                        rgba(255, 255, 255, 0.2);
                                "
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-wider mb-1"
                                    style="color: rgba(255, 255, 255, 0.75)"
                                >
                                    Total
                                </div>
                                <div
                                    class="text-[1.3rem] font-bold tabular-nums"
                                    style="color: #ffffff"
                                >
                                    {{ stats.totalSessions
                                    }}<span
                                        class="text-[11px] font-medium"
                                        style="color: rgba(255, 255, 255, 0.75)"
                                    >
                                        sessions</span
                                    >
                                </div>
                                <div
                                    class="text-[10px] font-medium"
                                    style="color: rgba(255, 255, 255, 0.65)"
                                >
                                    +{{ stats.thisWeek ?? 0 }} this week
                                </div>
                            </div>

                            <div class="pl-4">
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-wider mb-1"
                                    style="color: rgba(255, 255, 255, 0.75)"
                                >
                                    Done
                                </div>
                                <div
                                    class="text-[1.3rem] font-bold tabular-nums"
                                    style="color: #ffffff"
                                >
                                    {{ stats.completedSessions
                                    }}<span
                                        class="text-[11px] font-medium"
                                        style="color: rgba(255, 255, 255, 0.75)"
                                    >
                                        completed</span
                                    >
                                </div>
                                <div
                                    class="text-[10px] font-medium"
                                    style="color: rgba(255, 255, 255, 0.65)"
                                >
                                    +{{ stats.completedThisWeek ?? 0 }} this
                                    week
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end RIGHT COLUMN -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
