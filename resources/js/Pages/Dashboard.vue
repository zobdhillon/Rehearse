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

function scoreColor(score) {
    if (score === null || score === undefined)
        return { text: "var(--text-3)", bg: "var(--border)" };
    if (score >= 80) return { text: "var(--green)", bg: "var(--green-bg)" };
    if (score >= 60) return { text: "var(--amber)", bg: "var(--amber-bg)" };
    return { text: "var(--red)", bg: "var(--red-bg)" };
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
            isPending: value === 0,
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
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #title>Dashboard</template>

        <div
            class="flex h-full flex-col overflow-y-auto p-5 md:p-6 lg:overflow-hidden"
        >
            <div
                class="flex flex-col gap-5 lg:min-h-0 lg:flex-1 lg:flex-row lg:gap-6"
            >
                <!-- ── LEFT COLUMN ──────────────────────────────── -->
                <div
                    class="flex w-full flex-col gap-5 lg:h-full lg:min-h-0 lg:w-[62%] lg:flex-shrink-0 lg:overflow-hidden"
                >
                    <!-- BANNER CARD -->
                    <div
                        class="rounded-2xl overflow-hidden relative flex-shrink-0"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <!-- Decorative orbs — speech/mic shapes for interview coaching -->
                        <div
                            style="
                                position: absolute;
                                inset: 0;
                                overflow: hidden;
                                pointer-events: none;
                            "
                        >
                            <!-- Large orb -->
                            <div
                                style="
                                    position: absolute;
                                    right: -24px;
                                    top: -32px;
                                    width: 180px;
                                    height: 180px;
                                    border-radius: 50%;
                                    background: var(--accent-bg);
                                    opacity: 0.7;
                                "
                            />
                            <!-- Medium orb -->
                            <div
                                style="
                                    position: absolute;
                                    right: 80px;
                                    bottom: -20px;
                                    width: 110px;
                                    height: 110px;
                                    border-radius: 50%;
                                    background: var(--accent-bg);
                                    opacity: 0.45;
                                "
                            />
                            <!-- Small orb -->
                            <div
                                style="
                                    position: absolute;
                                    right: 200px;
                                    top: 10px;
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    background: var(--accent-bg);
                                    opacity: 0.3;
                                "
                            />
                        </div>

                        <!-- Content -->
                        <div
                            class="relative z-10 flex flex-col justify-between px-5 py-5 sm:px-7 sm:py-6"
                        >
                            <!-- Top row -->
                            <div>
                                <p
                                    class="mb-1 text-[11px] font-medium uppercase tracking-widest"
                                    style="color: var(--text-3)"
                                >
                                    {{ greeting }}, {{ userName }}
                                </p>
                                <h1
                                    class="text-xl font-bold leading-tight tracking-tight sm:text-[1.75rem]"
                                    style="color: var(--text)"
                                >
                                    {{
                                        stats.completedSessions > 0
                                            ? `${stats.completedSessions} sessions completed.`
                                            : "Ready to practice?"
                                    }}
                                </h1>
                                <p
                                    class="mt-1 text-[12px]"
                                    style="color: var(--text-3)"
                                >
                                    {{
                                        stats.bestScore
                                            ? `Best score ${stats.bestScore}/100 · ${currentLevel.title}`
                                            : "Pick a scenario below to start your first session."
                                    }}
                                </p>
                            </div>

                            <!-- Bottom row: stack on mobile, side-by-side from md -->
                            <div
                                class="mt-5 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
                            >
                                <div
                                    class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap"
                                >
                                    <Link
                                        :href="route('scenarios.index')"
                                        class="btn-primary justify-center px-4 py-2 text-center text-[12px]"
                                    >
                                        Start Practice
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
                                    class="w-full border-t pt-4 md:w-auto md:border-t-0 md:pt-0 md:text-right"
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
                                            ·
                                            <span class="hidden sm:inline">{{
                                                currentLevel.title
                                            }}</span>
                                            <span class="sm:hidden">{{
                                                currentLevel.title.split(" ")[0]
                                            }}</span>
                                        </span>
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
                    </div>

                    <!-- TWO STAT CARDS SIDE BY SIDE (stack below sm) -->
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-shrink-0"
                    >
                        <!-- Score trend card -->
                        <div
                            class="rounded-2xl p-5"
                            style="
                                background: var(--bg-surface);
                                border: 1px solid var(--border);
                                box-shadow: var(--shadow-md);
                            "
                        >
                            <div class="mb-1 flex items-start justify-between gap-2">
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
                                        {{ stats.bestScore }}
                                        <span
                                            class="text-[1rem] font-medium"
                                            style="color: var(--text-3)"
                                            >/100</span
                                        >
                                    </div>
                                    <div v-else class="mt-1.5">
                                        <div
                                            class="text-[15px] font-semibold leading-tight"
                                            style="color: var(--text-2)"
                                        >
                                            Not scored yet
                                        </div>
                                        <div
                                            class="mt-0.5 text-[10px] leading-snug"
                                            style="color: var(--text-3)"
                                        >
                                            Complete a session to track progress
                                        </div>
                                    </div>
                                </div>
                                <span
                                    v-if="stats.scoreImprovement"
                                    class="flex-shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold"
                                    style="
                                        background: var(--green-bg);
                                        color: var(--green);
                                    "
                                    >+{{ stats.scoreImprovement }} this
                                    month</span
                                >
                            </div>
                            <div
                                class="text-[11px] mb-3"
                                style="color: var(--text-3)"
                            >
                                Score over last 10 sessions
                            </div>

                            <!-- Area chart -->
                            <svg
                                v-if="hasScoreChart"
                                width="100%"
                                height="64"
                                viewBox="0 0 240 64"
                                preserveAspectRatio="none"
                                class="block"
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
                                            stop-opacity="0.32"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="var(--accent)"
                                            stop-opacity="0"
                                        />
                                    </linearGradient>
                                </defs>
                                <path
                                    :d="areaPath(scoreHistory, 240, 64)"
                                    fill="url(#scoreGrad)"
                                />
                                <path
                                    :d="linePath(scoreHistory, 240, 64)"
                                    fill="none"
                                    stroke="var(--accent)"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    :cx="sparkEndpoint.x"
                                    :cy="sparkEndpoint.y"
                                    r="3.5"
                                    fill="var(--bg-surface)"
                                    stroke="var(--accent)"
                                    stroke-width="2"
                                />
                            </svg>
                            <div
                                v-else
                                class="flex h-16 flex-col items-center justify-center gap-2 rounded-lg px-3"
                                style="background: var(--bg-surface2)"
                            >
                                <div
                                    class="flex h-8 w-full items-end justify-between gap-1 px-1"
                                >
                                    <span
                                        v-for="n in 6"
                                        :key="n"
                                        class="flex-1 rounded-sm"
                                        :style="{
                                            height: `${20 + n * 6}%`,
                                            background: 'var(--track-bg)',
                                        }"
                                    />
                                </div>
                                <span
                                    class="text-[10px] leading-snug text-center"
                                    style="color: var(--text-3)"
                                >
                                    Your score trend will appear here
                                </span>
                            </div>
                        </div>

                        <!-- Weekly activity card -->
                        <div
                            class="rounded-2xl p-5"
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
                                class="text-[11px] mb-3"
                                style="color: var(--text-3)"
                            >
                                Daily sessions Sun – Sat
                            </div>

                            <!-- 7-column dot matrix -->
                            <div
                                class="flex items-stretch justify-between gap-1.5"
                                style="height: 64px"
                            >
                                <div
                                    v-for="(count, idx) in activityHistory"
                                    :key="idx"
                                    class="flex flex-1 flex-col items-center"
                                >
                                    <!-- 7 stacked dots, evenly spaced to fill the column -->
                                    <div
                                        class="flex flex-1 w-full flex-col-reverse items-center justify-between py-0.5"
                                    >
                                        <span
                                            v-for="row in DOT_ROWS"
                                            :key="row"
                                            class="rounded-[2.5px] transition-colors duration-300"
                                            style="width: 8px; height: 6px"
                                            :style="{
                                                background:
                                                    row <= filledDots(count)
                                                        ? idx === todayIdx
                                                            ? 'var(--accent)'
                                                            : 'var(--accent-2)'
                                                        : 'var(--track-bg)',
                                                opacity:
                                                    row <= filledDots(count)
                                                        ? idx === todayIdx
                                                            ? 1
                                                            : 0.55
                                                        : 1,
                                            }"
                                        />
                                    </div>
                                    <!-- Day label, centered under its column -->
                                    <span
                                        class="mt-1.5 w-full text-center text-[9px] font-semibold leading-none"
                                        :style="
                                            idx === todayIdx
                                                ? 'color:var(--accent)'
                                                : 'color:var(--text-3)'
                                        "
                                    >
                                        {{ weekDays[idx][0] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- YOUR SKILLS CARD — grows to fill left column on desktop -->
                    <div
                        class="flex flex-shrink-0 flex-col rounded-2xl p-5 lg:min-h-0 lg:flex-1 lg:overflow-hidden"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <div
                            class="mb-4 flex flex-shrink-0 items-center justify-between"
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
                                class="text-[11px] font-medium hover:opacity-70 transition-opacity"
                                style="color: var(--accent)"
                            >
                                View details
                            </Link>
                        </div>

                        <div
                            class="flex flex-col items-center gap-4 sm:flex-row sm:items-stretch sm:gap-5 lg:min-h-0 lg:flex-1"
                        >
                            <!-- Radar chart -->
                            <div
                                class="flex w-full max-w-[220px] flex-shrink-0 items-center sm:w-[200px] lg:w-[180px]"
                            >
                                <svg
                                    viewBox="0 0 200 200"
                                    class="h-auto w-full"
                                    aria-label="Skills radar chart"
                                >
                                    <defs>
                                        <linearGradient
                                            id="radarFill"
                                            x1="0%"
                                            y1="0%"
                                            x2="100%"
                                            y2="100%"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="var(--accent)"
                                                stop-opacity="0.35"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="var(--accent-2)"
                                                stop-opacity="0.15"
                                            />
                                        </linearGradient>
                                    </defs>

                                    <g
                                        v-for="ring in radarGridRings"
                                        :key="ring"
                                    >
                                        <polygon
                                            :points="
                                                RADAR_ANGLES.map((a) => {
                                                    const p = radarPoint(
                                                        ring,
                                                        a,
                                                        RADAR_CX,
                                                        RADAR_CY,
                                                        RADAR_MAX_R,
                                                    );
                                                    return `${p.x},${p.y}`;
                                                }).join(' ')
                                            "
                                            fill="none"
                                            stroke="var(--track-bg)"
                                            stroke-width="1"
                                        />
                                    </g>

                                    <line
                                        v-for="skill in radarSkills"
                                        :key="`axis-${skill.key}`"
                                        :x1="RADAR_CX"
                                        :y1="RADAR_CY"
                                        :x2="skill.axis.x"
                                        :y2="skill.axis.y"
                                        stroke="var(--border)"
                                        stroke-width="1"
                                    />

                                    <polygon
                                        v-if="
                                            radarSkills.some((s) => !s.isPending)
                                        "
                                        :points="radarPolygon"
                                        fill="url(#radarFill)"
                                        stroke="var(--accent)"
                                        stroke-width="2"
                                        stroke-linejoin="round"
                                    />

                                    <circle
                                        v-for="skill in radarSkills"
                                        :key="`dot-${skill.key}`"
                                        v-show="!skill.isPending"
                                        :cx="skill.point.x"
                                        :cy="skill.point.y"
                                        r="3.5"
                                        fill="var(--bg-surface)"
                                        stroke="var(--accent)"
                                        stroke-width="2"
                                    />

                                    <text
                                        v-for="skill in radarSkills"
                                        :key="`label-${skill.key}`"
                                        :x="skill.labelPos.x"
                                        :y="skill.labelPos.y"
                                        text-anchor="middle"
                                        dominant-baseline="middle"
                                        class="text-[9px] font-semibold"
                                        fill="var(--text-2)"
                                    >
                                        {{
                                            skillAxisLabel(skill.key)
                                        }}
                                    </text>
                                </svg>
                            </div>

                            <!-- Skill legend -->
                            <div
                                class="grid w-full min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-rows-2 lg:content-stretch"
                            >
                                <div
                                    v-for="skill in skillsWithMeta"
                                    :key="skill.key"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 lg:h-full"
                                    style="
                                        background: var(--bg-surface2);
                                        border: 1px solid var(--border);
                                    "
                                >
                                    <div
                                        class="h-8 w-1 flex-shrink-0 rounded-full"
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
                                        <div class="mt-0.5 flex items-center gap-1.5">
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
                                                class="text-[10px] leading-tight"
                                                style="color: var(--text-3)"
                                            >
                                                Pending
                                            </span>
                                            <span
                                                v-else-if="skill.delta !== 0"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                style="
                                                    background: var(--green-bg);
                                                    color: var(--green);
                                                "
                                            >
                                                +{{ skill.delta }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end LEFT COLUMN -->

                <!-- ── RIGHT COLUMN ─────────────────────────────── -->
                <div
                    class="flex min-w-0 flex-1 flex-col gap-5 lg:h-full lg:min-h-0 lg:overflow-hidden"
                >
                    <!-- RECENT SESSIONS -->
                    <div
                        class="flex flex-shrink-0 flex-col overflow-hidden rounded-2xl lg:min-h-0 lg:flex-1"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <!-- Header (fixed, doesn't scroll) -->
                        <div
                            class="flex items-center justify-between px-5 py-4 border-b flex-shrink-0"
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
                                class="text-[11px] font-medium hover:opacity-70 transition-opacity"
                                style="color: var(--accent)"
                                >View all</Link
                            >
                        </div>

                        <!-- Session list -->
                        <div
                            class="min-h-0 flex-1 overflow-y-auto lg:flex lg:flex-col"
                        >
                            <!-- Empty state -->
                            <div
                                v-if="recentSessions.length === 0"
                                class="flex h-full min-h-[12rem] flex-col justify-between px-5 py-5"
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
                                    class="btn-primary mt-4 self-center text-[11px] px-4 py-2 lg:mt-0"
                                    >Browse Scenarios</Link
                                >
                            </div>

                            <!-- Session rows - scrollable -->
                            <div v-else>
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
                                        <span
                                            v-if="session.score !== null"
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
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
                                        <span
                                            class="rounded px-2 py-1 text-[10px] font-medium border"
                                            :style="
                                                session.is_completed
                                                    ? 'color: var(--text-2); border-color: var(--border-strong); background: transparent;'
                                                    : 'color: var(--accent); border-color: var(--accent); background: var(--accent-bg);'
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
                            </div>
                        </div>
                    </div>

                    <!-- STREAK + STAT -->
                    <div
                        class="flex-shrink-0 rounded-2xl p-5"
                        style="
                            background: var(--bg-surface);
                            border: 1px solid var(--border);
                            box-shadow: var(--shadow-md);
                        "
                    >
                        <div
                            class="grid grid-cols-3 divide-x"
                            style="--tw-divide-opacity: 1"
                        >
                            <div class="pr-4">
                                <div
                                    class="text-[10px] font-medium uppercase tracking-wider mb-1"
                                    style="color: var(--text-3)"
                                >
                                    Streak
                                </div>
                                <div
                                    class="text-[1.4rem] font-bold tabular-nums"
                                    style="color: var(--text)"
                                >
                                    {{ stats.currentStreak }}
                                    <span
                                        class="text-[11px] font-normal"
                                        style="color: var(--text-3)"
                                        >days</span
                                    >
                                </div>
                                <div
                                    class="text-[10px]"
                                    style="color: var(--text-3)"
                                >
                                    Best:
                                    {{
                                        stats.bestStreak ?? stats.currentStreak
                                    }}d
                                </div>
                            </div>

                            <div class="px-4">
                                <div
                                    class="text-[10px] font-medium uppercase tracking-wider mb-1"
                                    style="color: var(--text-3)"
                                >
                                    Total
                                </div>
                                <div
                                    class="text-[1.4rem] font-bold tabular-nums"
                                    style="color: var(--text)"
                                >
                                    {{ stats.totalSessions }}
                                    <span
                                        class="text-[11px] font-normal"
                                        style="color: var(--text-3)"
                                        >sessions</span
                                    >
                                </div>
                                <div
                                    class="text-[10px]"
                                    style="color: var(--text-3)"
                                >
                                    +{{ stats.thisWeek ?? 0 }} this week
                                </div>
                            </div>

                            <div class="pl-4">
                                <div
                                    class="text-[10px] font-medium uppercase tracking-wider mb-1"
                                    style="color: var(--text-3)"
                                >
                                    Done
                                </div>
                                <div
                                    class="text-[1.4rem] font-bold tabular-nums"
                                    style="color: var(--text)"
                                >
                                    {{ stats.completedSessions }}
                                    <span
                                        class="text-[11px] font-normal"
                                        style="color: var(--text-3)"
                                        >completed</span
                                    >
                                </div>
                                <div
                                    class="text-[10px]"
                                    style="color: var(--text-3)"
                                >
                                    +{{ stats.completedThisWeek ?? 0 }} this
                                    week
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
