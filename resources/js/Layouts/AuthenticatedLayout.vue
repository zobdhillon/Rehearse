<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";

const page = usePage();
const user = computed(() => page.props.auth.user);

// ── Sidebar state ─────────────────────────────────────────
const collapsed = ref(false);
const isMobile = ref(false);

let breakpoint = "desktop";
const syncViewport = () => {
    const w = window.innerWidth;
    isMobile.value = w < 768;
    const newBp = w < 768 ? "mobile" : w < 1025 ? "tablet" : "desktop";
    if (newBp !== breakpoint) {
        collapsed.value = newBp !== "desktop";
        breakpoint = newBp;
    }
};

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
};

// Called on nav link clicks — closes sidebar on mobile only
const closeSidebarOnMobile = () => {
    if (isMobile.value) collapsed.value = true;
};

const showBackdrop = computed(() => isMobile.value && !collapsed.value);

// ── Mobile smooth navigation ─────────────────────────────
let closeTimeout = null;

const handleMobileNavClick = (href, e) => {
    if (!isMobile.value || collapsed.value) {
        // Desktop or sidebar already closed – navigate immediately
        return true;
    }

    e.preventDefault(); // Stop Inertia from navigating right away

    // Close the sidebar
    collapsed.value = true;

    // Clear any previous timeout
    if (closeTimeout) clearTimeout(closeTimeout);

    // Wait for the sidebar collapse animation (matches duration-300 in CSS)
    closeTimeout = setTimeout(() => {
        router.visit(href);
        closeTimeout = null;
    }, 300);

    return false;
};

const handleMobileLogout = async (e) => {
    if (!isMobile.value || collapsed.value) {
        // Desktop or sidebar already closed – logout immediately
        router.post(route("logout"));
        return;
    }

    e.preventDefault();

    // Close the sidebar
    collapsed.value = true;

    if (closeTimeout) clearTimeout(closeTimeout);
    closeTimeout = setTimeout(() => {
        router.post(route("logout"));
        closeTimeout = null;
    }, 300);
};

// ── Theme ─────────────────────────────────────────────────
const isDark = ref(false);
const applyTheme = (dark) => {
    document.documentElement.classList.toggle("dark", dark);
    localStorage.setItem("theme", dark ? "dark" : "light");
    isDark.value = dark;
};
const toggleDark = () => applyTheme(!isDark.value);

// ── Lifecycle ─────────────────────────────────────────────
onMounted(() => {
    const saved = localStorage.getItem("theme");
    const prefersDark = window.matchMedia(
        "(prefers-color-scheme: dark)",
    ).matches;
    applyTheme(saved ? saved === "dark" : prefersDark);
    syncViewport();
    window.addEventListener("resize", syncViewport);
});

onUnmounted(() => {
    window.removeEventListener("resize", syncViewport);
    if (closeTimeout) clearTimeout(closeTimeout);
});

// ── User helpers ──────────────────────────────────────────
const initials = computed(() => {
    if (!user.value?.name) return "??";
    return user.value.name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
});

const upperName = computed(() => user.value?.name?.toUpperCase() ?? "");

const logout = () => router.post(route("logout"));
</script>

<template>
    <div
        class="relative h-screen overflow-hidden"
        style="background: var(--bg)"
    >
        <!-- Mobile backdrop — clicking closes sidebar -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showBackdrop"
                class="fixed inset-0 z-[98] bg-black/50"
                @click.stop="collapsed = true"
            />
        </Transition>

        <div class="relative flex h-full">
            <!-- SIDEBAR -->
            <aside
                :class="[
                    'flex flex-col flex-shrink-0 border-r transition-all duration-300 ease-in-out',
                    isMobile && collapsed
                        ? 'w-0 border-r-0 overflow-hidden'
                        : '',
                    isMobile && !collapsed
                        ? 'fixed inset-y-0 left-0 z-[150] w-[180px] overflow-hidden'
                        : '',
                    !isMobile && collapsed ? 'w-[52px] overflow-hidden' : '',
                    !isMobile && !collapsed ? 'w-[180px] overflow-hidden' : '',
                ]"
                style="
                    background: var(--bg);
                    border-color: var(--border-strong);
                "
            >
                <!-- Sidebar header -->
                <div
                    class="flex h-[56px] flex-shrink-0 items-center border-b"
                    style="border-color: var(--border)"
                >
                    <template v-if="!collapsed">
                        <div
                            class="flex flex-1 items-center gap-2 pl-4 min-w-0 overflow-hidden"
                        >
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg"
                                style="
                                    background: var(--gradient-accent);
                                    box-shadow: var(--shadow-btn);
                                "
                            >
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 18 18"
                                    fill="none"
                                >
                                    <rect
                                        x="3"
                                        y="2"
                                        width="3"
                                        height="14"
                                        rx="1.5"
                                        fill="white"
                                        opacity="0.95"
                                    />
                                    <path
                                        d="M6 2h4.5a3.5 3.5 0 0 1 0 7H6"
                                        stroke="white"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                        fill="none"
                                        opacity="0.95"
                                    />
                                    <path
                                        d="M9 9l4.5 7"
                                        stroke="white"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                        opacity="0.8"
                                    />
                                </svg>
                            </div>
                            <span
                                class="grad-text whitespace-nowrap text-sm font-extrabold tracking-tight"
                            >
                                Rehearse
                            </span>
                        </div>
                        <!-- X to close on mobile, collapse arrow on desktop -->
                        <button
                            v-if="isMobile"
                            @click.stop="collapsed = true"
                            class="mr-3 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border-0 bg-transparent transition-all duration-200"
                            style="color: var(--text-3)"
                        >
                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                        <button
                            v-else
                            @click.stop="collapsed = true"
                            title="Collapse sidebar"
                            class="mr-3 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border-0 bg-transparent text-[var(--text-3)] transition-all duration-200 hover:bg-[var(--accent-bg)] hover:text-[var(--accent)]"
                        >
                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="18"
                                    height="18"
                                    rx="2"
                                />
                                <line x1="9" y1="3" x2="9" y2="21" />
                                <polyline points="6 10 4.5 12 6 14" />
                            </svg>
                        </button>
                    </template>

                    <template v-else>
                        <button
                            @click.stop="collapsed = false"
                            title="Expand sidebar"
                            class="mx-auto flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent transition-all duration-200"
                            style="color: var(--text-3)"
                            onmouseover="
                                this.style.background = 'var(--accent-bg)';
                                this.style.color = 'var(--accent)';
                            "
                            onmouseout="
                                this.style.background = 'transparent';
                                this.style.color = 'var(--text-3)';
                            "
                        >
                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="18"
                                    height="18"
                                    rx="2"
                                />
                                <line x1="9" y1="3" x2="9" y2="21" />
                                <polyline points="12 10 13.5 12 12 14" />
                            </svg>
                        </button>
                    </template>
                </div>

                <!-- Nav links -->
                <nav class="flex-1 overflow-y-auto py-4">
                    <Link
                        v-for="item in [
                            {
                                href: route('dashboard'),
                                label: 'Dashboard',
                                active: route().current('dashboard'),
                                icon: 'M3 3h7v7H3zm11 0h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
                            },
                            {
                                href: route('scenarios.index'),
                                label: 'Scenarios',
                                active: route().current('scenarios.*'),
                                icon: 'M5 3l14 9-14 9V3z',
                            },
                            {
                                href: route('conversations.index'),
                                label: 'Sessions',
                                active: route().current('conversations.*'),
                                icon: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
                            },
                        ]"
                        :key="item.href"
                        :href="item.href"
                        :title="collapsed ? item.label : ''"
                        @click="handleMobileNavClick(item.href, $event)"
                        :class="[
                            'relative mx-2 my-1 flex items-center gap-2.5 rounded-xl px-2.5 py-2.5 text-xs font-medium transition-all duration-200',
                            collapsed ? 'justify-center' : '',
                            item.active
                                ? 'font-semibold'
                                : 'hover:bg-[var(--accent-bg)] hover:text-[var(--accent)]',
                        ]"
                        :style="
                            item.active
                                ? 'background: var(--accent-bg); color: var(--accent); border-left: 3px solid var(--accent); padding-left: 9px;'
                                : 'color: var(--text);'
                        "
                    >
                        <svg
                            class="h-[15px] w-[15px] flex-shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path :d="item.icon" />
                        </svg>
                        <span v-if="!collapsed" class="truncate">{{
                            item.label
                        }}</span>
                    </Link>
                </nav>

                <!-- Sidebar footer -->
                <div
                    class="flex-shrink-0 border-t pb-3 pt-2"
                    style="border-color: var(--border)"
                >
                    <Link
                        :href="route('profile.edit')"
                        :title="collapsed ? 'Edit profile' : ''"
                        @click="
                            handleMobileNavClick(route('profile.edit'), $event)
                        "
                        :class="[
                            'mx-2 flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition-all duration-200 hover:bg-[var(--accent-bg)] hover:text-[var(--accent)]',
                            collapsed ? 'justify-center' : '',
                        ]"
                        style="color: var(--text-2)"
                    >
                        <div
                            class="avatar !h-[26px] !w-[26px] !text-[10px] flex-shrink-0"
                        >
                            {{ initials }}
                        </div>
                        <div v-if="!collapsed" class="min-w-0 overflow-hidden">
                            <div
                                class="truncate text-[11px] font-semibold"
                                style="color: var(--text)"
                            >
                                {{ upperName }}
                            </div>
                            <div
                                class="text-[10px]"
                                style="color: var(--text-3)"
                            >
                                Edit profile →
                            </div>
                        </div>
                    </Link>

                    <button
                        @click="logout"
                        :title="collapsed ? 'Log out' : ''"
                        :class="[
                            'mx-2 mt-0.5 flex w-[calc(100%-16px)] items-center gap-2.5 rounded-xl border-0 bg-transparent px-2.5 py-2 font-[inherit] text-xs transition-all duration-200',
                            collapsed ? 'justify-center' : '',
                        ]"
                        style="color: var(--red); cursor: pointer"
                        onmouseover="this.style.background = 'var(--red-bg)'"
                        onmouseout="this.style.background = 'transparent'"
                    >
                        <svg
                            class="h-[15px] w-[15px] flex-shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span v-if="!collapsed">Log out</span>
                    </button>
                </div>
            </aside>

            <!-- MAIN COLUMN -->
            <div
                class="flex min-w-0 flex-1 flex-col overflow-hidden max-w-[1440px] mx-auto"
                style="background: var(--bg-surface)"
            >
                <!-- Topbar -->
                <header
                    class="relative flex h-[56px] flex-shrink-0 items-center justify-between border-b px-4 md:px-6"
                    style="
                        background: var(--bg-surface);
                        border-color: var(--border-strong);
                    "
                >
                    <div class="flex min-w-0 items-center gap-2 flex-1">
                        <!-- Hamburger — mobile only -->
                        <button
                            v-if="isMobile"
                            @click.stop="toggleSidebar"
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg border-0 bg-transparent transition-all duration-200"
                            style="color: var(--text-2)"
                            aria-label="Open menu"
                        >
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
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <line x1="3" y1="12" x2="21" y2="12" />
                                <line x1="3" y1="18" x2="21" y2="18" />
                            </svg>
                        </button>

                        <h1
                            class="truncate text-[13px] sm:text-[15px] font-extrabold leading-tight tracking-tight"
                            style="color: var(--text)"
                        >
                            <slot name="title">Rehearse</slot>
                        </h1>
                    </div>

                    <!-- Right controls -->
                    <div class="flex items-center gap-1 sm:gap-2">
                        <button
                            @click="toggleDark"
                            :title="
                                isDark
                                    ? 'Switch to light mode'
                                    : 'Switch to dark mode'
                            "
                            class="flex h-8 w-8 items-center justify-center rounded-lg border-0 bg-transparent transition-all duration-200"
                            style="color: var(--text-3)"
                            onmouseover="
                                this.style.background = 'var(--accent-bg)';
                                this.style.color = 'var(--accent)';
                            "
                            onmouseout="
                                this.style.background = 'transparent';
                                this.style.color = 'var(--text-3)';
                            "
                        >
                            <svg
                                v-if="isDark"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="5" />
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line
                                    x1="18.36"
                                    y1="18.36"
                                    x2="19.78"
                                    y2="19.78"
                                />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line
                                    x1="4.22"
                                    y1="19.78"
                                    x2="5.64"
                                    y2="18.36"
                                />
                                <line
                                    x1="18.36"
                                    y1="5.64"
                                    x2="19.78"
                                    y2="4.22"
                                />
                            </svg>
                            <svg
                                v-else
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"
                                />
                            </svg>
                        </button>

                        <div class="hidden md:block text-right">
                            <div
                                class="text-[11px] font-semibold"
                                style="color: var(--text)"
                            >
                                {{ upperName }}
                            </div>
                        </div>

                        <div class="avatar !h-8 sm:!h-9 !w-8 sm:!w-9 !text-xs">
                            {{ initials }}
                        </div>
                    </div>
                </header>

                <!-- Page content -->
                <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div
                        class="min-h-0 flex-1 overflow-y-auto"
                        style="background: var(--bg)"
                    >
                        <Transition name="v" mode="out-in">
                            <div :key="$page.url" class="min-h-full">
                                <slot />
                            </div>
                        </Transition>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
