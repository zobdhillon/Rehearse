<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, onBeforeUnmount } from "vue";

/* ── Copy (edit here) ─────────────────────────────────────── */
const COPY = {
    headline: "The words come easier when you’ve rehearsed.",
    sub: "Practice real conversations with an AI coach, at your own pace.",
    boxLabel: "Describe a conversation you want to practice",
    prompts: [
        "My manager wants an update on a delayed project…",
        "I need to ask for a raise and I’m nervous…",
        "A customer is angry about a late order…",
        "I have a software engineer interview on Friday…",
    ],
    chips: [
        "Ask my manager for a raise",
        "Handle an angry customer",
        "Software engineer interview",
        "Share difficult feedback",
    ],
    flowTitle: "A little practice changes the conversation.",
    flow: [
        {
            title: "Pick a scenario",
            text: "Start with what’s on your mind.",
        },
        {
            title: "Rehearse with your coach",
            text: "Work through it one message at a time.",
        },
        {
            title: "Review your feedback",
            text: "See how you did and track it over time.",
        },
    ],
    feedbackTitle: "A better conversation starts with a clearer first line.",
    cta: "Ready to find your words?",
};

/* Sample sentence + the feedback attached to each highlighted phrase */
const sentence = [
    { t: "I’d like to understand " },
    { t: "what matters most", n: 0 },
    { t: " on this project, share where I can make " },
    { t: "the biggest contribution", n: 1 },
    { t: ", and " },
    { t: "agree on a clear next step", n: 2 },
    { t: "; if priorities have shifted, " },
    { t: "I’m happy to adjust", n: 3 },
    { t: "." },
];
const notes = [
    {
        label: "Clarity",
        text: "Names the priority before jumping to a solution.",
    },
    {
        label: "Objective",
        text: "Connects your contribution to what the project needs.",
    },
    { label: "Confidence", text: "Suggests a concrete way to move forward." },
    {
        label: "Adaptability",
        text: "Keeps the conversation open to new information.",
    },
];
const activeNote = ref(null);

/* ── Chat box ─────────────────────────────────────────────── */
const draft = ref("");
const focused = ref(false);
const placeholder = ref(COPY.prompts[0]);
const textarea = ref(null);

function pickChip(text) {
    draft.value = text;
    textarea.value?.focus();
}
function startPracticing() {
    // Public page: never calls an AI/API. Guests go to register.
    router.visit(route("register"));
}

/* Typing placeholder (the one motion on the page) */
let timer = null;
let pi = 0;
let ci = 0;
let deleting = false;

function tick() {
    if (focused.value || draft.value.length > 0) {
        timer = setTimeout(tick, 600);
        return;
    }
    const full = COPY.prompts[pi];
    if (!deleting) {
        ci++;
        placeholder.value = full.slice(0, ci);
        if (ci >= full.length) {
            deleting = true;
            timer = setTimeout(tick, 1800);
            return;
        }
        timer = setTimeout(tick, 42);
    } else {
        ci--;
        placeholder.value = full.slice(0, ci) || " ";
        if (ci <= 0) {
            deleting = false;
            pi = (pi + 1) % COPY.prompts.length;
            timer = setTimeout(tick, 400);
            return;
        }
        timer = setTimeout(tick, 20);
    }
}

/* ── Theme (dark by default, same class the app uses) ─────── */
const THEME_KEY = "theme";
const isDark = ref(true);

function applyTheme(dark) {
    document.documentElement.classList.toggle("dark", dark);
    isDark.value = dark;
}
function toggleTheme() {
    const next = !isDark.value;
    applyTheme(next);
    try {
        localStorage.setItem(THEME_KEY, next ? "dark" : "light");
    } catch (e) {
        /* storage can be unavailable */
    }
}

onMounted(() => {
    let stored = null;
    try {
        stored = localStorage.getItem(THEME_KEY);
    } catch (e) {
        /* ignore */
    }
    applyTheme(stored !== "light");

    const reduce = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;
    if (reduce) {
        placeholder.value = COPY.prompts[0];
    } else {
        timer = setTimeout(tick, 900);
    }
});
onBeforeUnmount(() => clearTimeout(timer));

const year = computed(() => new Date().getFullYear());
</script>

<template>
    <Head title="Rehearse – practice the conversations that matter" />

    <div class="landing">
        <a href="#main" class="skip">Skip to main content</a>

        <!-- ── Floating pill navbar ──────────────────────── -->
        <header class="nav">
            <a href="#top" class="brand" aria-label="Rehearse home">
                <span class="brand-mark" aria-hidden="true">
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                        />
                    </svg>
                </span>
                Rehearse
            </a>
            <nav class="nav-links" aria-label="Page sections">
                <a href="#how">How it works</a>
                <a href="#feedback">Feedback</a>
            </nav>
            <div class="nav-actions">
                <button
                    type="button"
                    class="icon-btn"
                    :aria-label="
                        isDark ? 'Switch to light mode' : 'Switch to dark mode'
                    "
                    @click="toggleTheme"
                >
                    <svg
                        v-if="isDark"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="4" />
                        <path
                            d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
                        />
                    </svg>
                    <svg
                        v-else
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"
                        />
                    </svg>
                </button>
                <Link :href="route('login')" class="nav-login">Log in</Link>
                <Link :href="route('register')" class="pill pill-solid pill-sm"
                    >Get started</Link
                >
            </div>
        </header>

        <main id="main">
            <!-- ── Hero ───────────────────────────────────── -->
            <section id="top" class="hero">
                <div class="hero-inner">
                    <h1 class="hero-title">{{ COPY.headline }}</h1>
                    <p class="hero-sub">{{ COPY.sub }}</p>

                    <div class="hero-buttons">
                        <Link :href="route('register')" class="pill pill-solid"
                            >Get started free</Link
                        >
                        <Link :href="route('login')" class="pill pill-ghost"
                            >Log in</Link
                        >
                    </div>

                    <div class="chatbox">
                        <label for="draft" class="sr-only">{{
                            COPY.boxLabel
                        }}</label>
                        <textarea
                            id="draft"
                            ref="textarea"
                            v-model="draft"
                            rows="3"
                            :placeholder="placeholder"
                            @focus="focused = true"
                            @blur="focused = false"
                            @keydown.enter.exact.prevent="startPracticing"
                        />
                        <div class="chatbox-bar">
                            <span class="chatbox-hint"
                                >Free to start. You’ll create an account
                                next.</span
                            >
                            <button
                                type="button"
                                class="pill pill-solid pill-sm"
                                @click="startPracticing"
                            >
                                Start practicing
                            </button>
                        </div>
                    </div>

                    <ul class="chips" aria-label="Example scenarios">
                        <li v-for="chip in COPY.chips" :key="chip">
                            <button
                                type="button"
                                class="chip"
                                @click="pickChip(chip)"
                            >
                                {{ chip }}
                            </button>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ── How it works: connected panels ─────────── -->
            <section id="how" class="section">
                <div class="wrap">
                    <h2 class="section-title">{{ COPY.flowTitle }}</h2>

                    <div class="flow">
                        <!-- Panel 1: scenarios -->
                        <article class="panel">
                            <div class="panel-visual">
                                <ul class="mini-list">
                                    <li class="mini-row mini-row-on">
                                        <span class="dot"></span>Ask for a raise
                                    </li>
                                    <li class="mini-row">
                                        <span class="dot"></span>Talk through a
                                        project
                                    </li>
                                    <li class="mini-row">
                                        <span class="dot"></span>Prepare for an
                                        interview
                                    </li>
                                </ul>
                            </div>
                            <h3>{{ COPY.flow[0].title }}</h3>
                            <p>{{ COPY.flow[0].text }}</p>
                        </article>

                        <!-- Panel 2: chat -->
                        <article class="panel">
                            <div class="panel-visual">
                                <div class="bubble bubble-coach">
                                    Your manager wants to discuss a challenging
                                    project. How would you start?
                                </div>
                                <div class="bubble bubble-you">
                                    I’d start by asking what matters most to
                                    them.
                                </div>
                            </div>
                            <h3>{{ COPY.flow[1].title }}</h3>
                            <p>{{ COPY.flow[1].text }}</p>
                        </article>

                        <!-- Panel 3: feedback ring -->
                        <article class="panel">
                            <div class="panel-visual panel-visual-center">
                                <svg
                                    viewBox="0 0 120 120"
                                    width="112"
                                    height="112"
                                    role="img"
                                    aria-label="Sample average score, 82 percent"
                                >
                                    <circle
                                        cx="60"
                                        cy="60"
                                        r="50"
                                        fill="none"
                                        stroke="var(--l-border2)"
                                        stroke-width="10"
                                    />
                                    <circle
                                        cx="60"
                                        cy="60"
                                        r="50"
                                        fill="none"
                                        stroke="var(--l-accent)"
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        transform="rotate(-90 60 60)"
                                        stroke-dasharray="314"
                                        stroke-dashoffset="56.5"
                                    />
                                    <text
                                        x="60"
                                        y="64"
                                        text-anchor="middle"
                                        font-size="26"
                                        font-weight="800"
                                        fill="var(--l-text)"
                                    >
                                        82%
                                    </text>
                                </svg>
                                <span class="sample-tag">Sample data</span>
                            </div>
                            <h3>{{ COPY.flow[2].title }}</h3>
                            <p>{{ COPY.flow[2].text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ── Feedback moment ────────────────────────── -->
            <section id="feedback" class="section section-tint">
                <div class="wrap">
                    <h2 class="section-title">{{ COPY.feedbackTitle }}</h2>

                    <div class="feedback">
                        <p class="sentence">
                            <template v-for="(part, i) in sentence" :key="i">
                                <span v-if="part.n === undefined">{{
                                    part.t
                                }}</span>
                                <mark
                                    v-else
                                    :class="{ on: activeNote === part.n }"
                                    @mouseenter="activeNote = part.n"
                                    @mouseleave="activeNote = null"
                                    >{{ part.t
                                    }}<sup>{{ part.n + 1 }}</sup></mark
                                >
                            </template>
                        </p>

                        <ul class="notes">
                            <li v-for="(note, i) in notes" :key="note.label">
                                <button
                                    type="button"
                                    class="note"
                                    :class="{ on: activeNote === i }"
                                    @mouseenter="activeNote = i"
                                    @mouseleave="activeNote = null"
                                    @focus="activeNote = i"
                                    @blur="activeNote = null"
                                >
                                    <span class="note-n">{{ i + 1 }}</span>
                                    <span>
                                        <strong>{{ note.label }}</strong>
                                        <span class="note-text">{{
                                            note.text
                                        }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <Link :href="route('register')" class="session-row">
                        <span class="session-icon" aria-hidden="true">
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                />
                            </svg>
                        </span>
                        <span class="session-info">
                            <span class="session-title"
                                >Discussing project priorities</span
                            >
                            <span class="sample-tag">Sample session</span>
                        </span>
                        <span class="session-right">
                            <span class="score-pill">82/100</span>
                            <span class="review">Review</span>
                        </span>
                    </Link>
                </div>
            </section>

            <!-- ── Final call to action ───────────────────── -->
            <section class="section section-cta">
                <div class="wrap">
                    <div class="cta">
                        <h2>{{ COPY.cta }}</h2>
                        <Link :href="route('register')" class="pill pill-light"
                            >Get started free</Link
                        >
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div class="wrap footer-inner">
                <span>© {{ year }} Rehearse. Built by Zobia Dhillon.</span>
                <span class="footer-links">
                    <a
                        href="https://github.com/zobdhillon"
                        target="_blank"
                        rel="noopener"
                        >GitHub</a
                    >
                    <a
                        href="https://www.linkedin.com/in/zobdhillon/"
                        target="_blank"
                        rel="noopener"
                        >LinkedIn</a
                    >
                </span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Local aliases: use the app's theme variables, with safe fallbacks */
.landing {
    --l-bg: var(--bg, #0b1220);
    --l-surface: var(--bg-surface, #111a2b);
    --l-surface2: var(--bg-surface2, #16213a);
    --l-text: var(--text, #e8eef7);
    --l-text2: var(--text-2, #aab6c8);
    --l-text3: var(--text-3, #7d8aa0);
    --l-accent: var(--accent, #14b8a6);
    --l-accent2: var(--accent-2, #0f9d8f);
    --l-border: var(--border, rgba(255, 255, 255, 0.08));
    --l-border2: var(--border-strong, rgba(255, 255, 255, 0.18));
    --l-grad: var(
        --gradient-primary,
        linear-gradient(135deg, #14b8a6, #0f7c6e)
    );

    min-height: 100vh;
    background: var(--l-bg);
    color: var(--l-text);
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
.landing *,
.landing *::before,
.landing *::after {
    box-sizing: border-box;
}
.landing a {
    color: inherit;
    text-decoration: none;
}
.landing :focus-visible {
    outline: 2px solid var(--l-accent);
    outline-offset: 3px;
}
.wrap {
    width: min(1100px, calc(100% - 40px));
    margin-inline: auto;
}

/* Accessibility helpers */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.skip {
    position: absolute;
    left: 12px;
    top: -60px;
    z-index: 100;
    padding: 10px 16px;
    border-radius: 999px;
    background: var(--l-accent);
    color: #04201c;
    font-weight: 700;
    transition: top 0.15s;
}
.skip:focus {
    top: 12px;
}

/* ── Buttons ─────────────────────────────────────────────── */
.pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 26px;
    border-radius: 999px;
    border: 1px solid transparent;
    font: inherit;
    font-weight: 700;
    font-size: 15px;
    white-space: nowrap;
    cursor: pointer;
    transition:
        transform 0.15s,
        box-shadow 0.15s,
        background 0.15s;
}
.pill-sm {
    padding: 10px 18px;
    font-size: 14px;
}
.pill-solid {
    background: var(--l-grad);
    color: #fff;
    box-shadow: 0 8px 24px color-mix(in srgb, var(--l-accent) 35%, transparent);
}
.pill-solid:hover {
    transform: translateY(-1px);
}
.pill-ghost {
    background: color-mix(in srgb, var(--l-surface) 70%, transparent);
    border-color: var(--l-border2);
    color: var(--l-text);
}
.pill-ghost:hover {
    background: var(--l-surface2);
}
.landing .pill-light {
    background: #fff;
    color: #0b4f47;
}
.landing .pill-solid {
    color: #fff;
}
.landing .pill-ghost {
    color: var(--l-text);
}
.icon-btn {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid var(--l-border);
    background: transparent;
    color: var(--l-text2);
    cursor: pointer;
}
.icon-btn:hover {
    color: var(--l-text);
    background: var(--l-surface2);
}

/* ── Navbar ──────────────────────────────────────────────── */
.nav {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 50;
    width: min(1100px, calc(100% - 32px));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 8px 10px 8px 16px;
    border-radius: 999px;
    border: 1px solid var(--l-border);
    background: color-mix(in srgb, var(--l-surface) 92%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: 17px;
    letter-spacing: -0.01em;
}
.brand-mark {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--l-grad);
    color: #fff;
}
.nav-links {
    display: flex;
    gap: 28px;
    font-size: 14px;
    font-weight: 600;
    color: var(--l-text2);
}
.nav-links a:hover {
    color: var(--l-text);
}
.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.nav-login {
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--l-text2);
}
.nav-login:hover {
    color: var(--l-text);
}

/* ── Hero ────────────────────────────────────────────────── */
.hero {
    position: relative;
    overflow: hidden;
    padding: 168px 20px 96px;
    text-align: center;
}
.hero::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
        radial-gradient(
            ellipse 55% 45% at 50% 0%,
            color-mix(in srgb, var(--l-accent) 30%, transparent),
            transparent 70%
        ),
        radial-gradient(
                color-mix(in srgb, var(--l-text) 16%, transparent) 1px,
                transparent 1.4px
            )
            0 0 / 24px 24px;
    -webkit-mask-image: linear-gradient(to bottom, #000 40%, transparent 100%);
    mask-image: linear-gradient(to bottom, #000 40%, transparent 100%);
}
.hero-inner {
    position: relative;
    z-index: 1;
    max-width: 820px;
    margin-inline: auto;
}
.hero-title {
    margin: 0;
    font-size: clamp(2.4rem, 6.4vw, 4.6rem);
    font-weight: 800;
    line-height: 1.04;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.hero-sub {
    margin: 22px auto 0;
    max-width: 52ch;
    font-size: clamp(1rem, 2vw, 1.2rem);
    color: var(--l-text2);
}
.hero-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
    margin-top: 32px;
}

.chatbox {
    margin: 48px auto 0;
    max-width: 720px;
    text-align: left;
    border-radius: 24px;
    border: 1px solid var(--l-border2);
    background: color-mix(in srgb, var(--l-surface) 72%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
}
.chatbox textarea {
    display: block;
    width: 100%;
    padding: 22px 24px 8px;
    border: 0;
    background: transparent;
    color: var(--l-text);
    font: inherit;
    font-size: 16px;
    resize: none;
}
.chatbox textarea::placeholder {
    color: var(--l-text3);
}
.chatbox textarea:focus {
    outline: none;
}
.chatbox:focus-within {
    border-color: var(--l-accent);
}
.chatbox-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px 14px 24px;
}
.chatbox-hint {
    font-size: 13px;
    color: var(--l-text3);
}

.chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin: 20px 0 0;
    padding: 0;
    list-style: none;
}
.chip {
    padding: 10px 16px;
    border-radius: 999px;
    border: 1px solid var(--l-border2);
    background: color-mix(in srgb, var(--l-surface) 60%, transparent);
    color: var(--l-text2);
    font: inherit;
    font-size: 14px;
    cursor: pointer;
}
.chip:hover {
    color: var(--l-text);
    border-color: var(--l-accent);
}

/* ── Sections ────────────────────────────────────────────── */
.section {
    padding: 104px 0;
    scroll-margin-top: 80px;
}
.section-tint {
    background: color-mix(in srgb, var(--l-surface) 70%, transparent);
    border-block: 1px solid var(--l-border);
}
.section-title {
    margin: 0 0 56px;
    max-width: 18ch;
    font-size: clamp(1.9rem, 4.2vw, 3rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    text-wrap: balance;
}
.sample-tag {
    display: inline-block;
    font-size: 12px;
    color: var(--l-text3);
}

/* Flow: three dashed panels joined by dotted connectors */
.flow {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 64px;
    align-items: stretch;
}
.panel {
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 22px;
    border-radius: 22px;
    border: 1.5px dashed var(--l-border2);
}
.panel:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 50%;
    right: -64px;
    width: 64px;
    border-top: 2px dotted var(--l-accent);
}
.panel-visual {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    min-height: 168px;
    margin-bottom: 20px;
}
.panel-visual-center {
    align-items: center;
}
.panel h3 {
    margin: 0 0 4px;
    font-size: 17px;
    font-weight: 700;
}
.panel p {
    margin: 0;
    font-size: 14px;
    color: var(--l-text2);
}
.mini-list {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.mini-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 14px;
    color: var(--l-text2);
}
.mini-row-on {
    background: color-mix(in srgb, var(--l-accent) 14%, transparent);
    color: var(--l-text);
    box-shadow: inset 0 0 0 1px var(--l-accent);
}
.dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--l-accent);
}
.bubble {
    max-width: 88%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.4;
}
.bubble-coach {
    background: var(--l-surface2);
    border: 1px solid var(--l-border);
    border-bottom-left-radius: 4px;
}
.bubble-you {
    align-self: flex-end;
    background: var(--l-grad);
    color: #fff;
    border-bottom-right-radius: 4px;
}

/* Feedback: annotated sentence + notes */
.feedback {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
    gap: 64px;
    align-items: start;
}
.sentence {
    margin: 0;
    font-size: clamp(1.4rem, 2.6vw, 2.1rem);
    font-weight: 500;
    line-height: 1.5;
    letter-spacing: -0.015em;
}
.sentence mark {
    padding: 2px 3px;
    border-radius: 6px;
    background: color-mix(in srgb, var(--l-accent) 20%, transparent);
    color: inherit;
    box-shadow: inset 0 -2px 0 var(--l-accent);
    cursor: default;
    transition: background 0.15s;
}
.sentence mark.on {
    background: color-mix(in srgb, var(--l-accent) 42%, transparent);
}
.sentence sup {
    margin-left: 2px;
    font-size: 0.5em;
    font-weight: 700;
    color: var(--l-accent);
}
.notes {
    display: grid;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.note {
    display: flex;
    gap: 14px;
    width: 100%;
    padding: 14px 16px;
    border: 0;
    border-left: 2px solid var(--l-border2);
    border-radius: 0 12px 12px 0;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: default;
    transition:
        background 0.15s,
        border-color 0.15s;
}
.note.on {
    border-left-color: var(--l-accent);
    background: color-mix(in srgb, var(--l-accent) 10%, transparent);
}
.note-n {
    flex: none;
    width: 22px;
    height: 22px;
    margin-top: 1px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    background: color-mix(in srgb, var(--l-accent) 18%, transparent);
    color: var(--l-accent);
}
.note strong {
    display: block;
    font-size: 15px;
}
.note-text {
    display: block;
    font-size: 14px;
    color: var(--l-text2);
}
.session-row {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 56px;
    padding: 16px 20px;
    border-radius: 18px;
    border: 1px solid var(--l-border2);
    background: color-mix(in srgb, var(--l-surface) 80%, transparent);
    transition:
        border-color 0.15s,
        background 0.15s;
}
.session-row:hover {
    border-color: var(--l-accent);
    background: color-mix(in srgb, var(--l-accent) 8%, var(--l-surface));
}
.session-icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: color-mix(in srgb, var(--l-accent) 16%, transparent);
    color: var(--l-accent);
}
.session-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.session-title {
    font-size: 16px;
    font-weight: 700;
}
.session-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.score-pill {
    padding: 4px 12px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--green, #22c55e) 16%, transparent);
    color: var(--green, #22c55e);
    font-size: 13px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.review {
    padding: 7px 16px;
    border-radius: 999px;
    border: 1px solid var(--l-border2);
    color: var(--l-text2);
    font-size: 13px;
    font-weight: 700;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}
.session-row:hover .review {
    background: var(--l-accent);
    border-color: var(--l-accent);
    color: #04201c;
}

/* CTA + footer */
.section-cta {
    padding-bottom: 72px;
}
.cta {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 56px 48px;
    border-radius: 28px;
    background: var(--l-grad);
    color: #fff;
    box-shadow: 0 24px 60px color-mix(in srgb, var(--l-accent) 28%, transparent);
}
.cta::before {
    content: "";
    position: absolute;
    top: -40%;
    right: -10%;
    width: 60%;
    height: 180%;
    background: radial-gradient(
        closest-side,
        rgba(255, 255, 255, 0.22),
        transparent
    );
    pointer-events: none;
}
.cta h2 {
    position: relative;
    margin: 0;
    max-width: 22ch;
    font-size: clamp(1.7rem, 3.6vw, 2.6rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
}
.cta .pill {
    position: relative;
}
.footer {
    border-top: 1px solid var(--l-border);
    padding: 28px 0 36px;
    font-size: 14px;
    color: var(--l-text3);
}
.footer-inner {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 12px;
}
.footer-links {
    display: flex;
    gap: 20px;
}
.footer-links a:hover {
    color: var(--l-text);
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 900px) {
    .flow {
        grid-template-columns: 1fr;
        gap: 56px;
    }
    .panel:not(:last-child)::after {
        top: 100%;
        right: auto;
        left: 50%;
        width: 0;
        height: 56px;
        border-top: 0;
        border-left: 2px dotted var(--l-accent);
    }
    .feedback {
        grid-template-columns: 1fr;
        gap: 32px;
    }
}
@media (max-width: 680px) {
    .nav-links,
    .nav-login {
        display: none;
    }
    .hero {
        padding-top: 128px;
    }
    .section {
        padding: 72px 0;
    }
    .chatbox-bar {
        flex-direction: column;
        align-items: stretch;
        padding-left: 14px;
    }
    .chatbox-hint {
        text-align: center;
    }
    .cta {
        padding: 40px 28px;
    }
    .session-row {
        flex-wrap: wrap;
    }
    .session-right {
        width: 100%;
        justify-content: space-between;
    }
}
@media (prefers-reduced-motion: reduce) {
    .landing * {
        transition: none !important;
    }
}
</style>
