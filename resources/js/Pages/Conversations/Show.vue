<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch, computed, nextTick, onMounted } from "vue";

const props = defineProps({
    conversation: Object,
});

const localMessages = ref([...props.conversation.messages]);
const messagesEl = ref(null);
const messageText = ref("");
const processing = ref(false);
const isSending = ref(false);
const isStarting = ref(false);
const errorMessage = ref(null);
const lastAttemptedMessage = ref("");

const isCompleted = ref(props.conversation.status === "completed");
const scores = ref(props.conversation.scores);

const userMessageCount = computed(
    () => localMessages.value.filter((m) => m.role === "user").length,
);

watch(
    () => props.conversation.status,
    (val) => {
        isCompleted.value = val === "completed";
    },
);

watch(
    () => props.conversation.scores,
    (val) => {
        scores.value = val;
    },
);
const showEndConfirm = ref(false);

const endSession = () => {
    showEndConfirm.value = false;
    router.post(`/conversations/${props.conversation.id}/complete`);
};

// Voice input
const isListening = ref(false);
let recognition = null;
let savedText = "";

const toggleVoice = () => {
    // Check browser support
    if (
        !("webkitSpeechRecognition" in window) &&
        !("SpeechRecognition" in window)
    ) {
        alert(
            "Voice input is not supported in your browser. Please use Chrome.",
        );
        return;
    }

    if (isListening.value) {
        recognition.stop();
        return;
    }

    const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();

    recognition.lang = "en-US";
    recognition.continuous = true;
    recognition.interimResults = true;

    savedText = messageText.value ? messageText.value + " " : "";

    recognition.onstart = () => {
        isListening.value = true;
    };

    recognition.onresult = (event) => {
        const transcript = Array.from(event.results)
            .map((result) => result[0].transcript)
            .join("");
        messageText.value = savedText + transcript;
    };

    recognition.onend = () => {
        isListening.value = false;
        savedText = messageText.value ? messageText.value + " " : "";
    };

    recognition.onerror = () => {
        isListening.value = false;
    };

    recognition.start();
};

watch(
    () => props.conversation.messages,
    (newMessages) => {
        if (newMessages.length >= localMessages.value.length) {
            localMessages.value = [...newMessages];
        }
    },
    { deep: true },
);

// Scroll to bottom whenever a new message appears
watch(
    () => localMessages.value.length,
    async () => {
        await nextTick();
        scrollToBottom();
    },
);

watch(processing, async (isProcessing) => {
    if (isProcessing) {
        await nextTick();
        scrollToBottom();
    }
});

function scrollToBottom() {
    const el = messagesEl.value;
    if (el) el.scrollTop = el.scrollHeight;
}

onMounted(() => nextTick(scrollToBottom));

const isUser = (role) => role === "user";

const sendMessage = () => {
    if (!messageText.value.trim() || isSending.value) return;

    errorMessage.value = null;
    const payload = messageText.value;
    lastAttemptedMessage.value = payload;

    // Optimistically push the user message immediately
    const tempMsg = {
        id: Date.now(),
        role: "user",
        content: payload,
    };
    localMessages.value.push(tempMsg);

    messageText.value = "";
    isSending.value = true;

    axios
        .post(`/conversations/${props.conversation.id}/messages`, {
            message_text: payload,
        })
        .then((response) => {
            if (response.data.auto_complete) {
                scores.value = response.data.scores;
                isCompleted.value = true;
            } else {
                localMessages.value.push(response.data.message);
            }
        })
        .catch((error) => {
            localMessages.value = localMessages.value.filter(
                (m) => m.id !== tempMsg.id,
            );
            let msg = "Something went wrong. Please try again.";
            if (
                error.response &&
                error.response.data &&
                error.response.data.message
            ) {
                msg = error.response.data.message;
            } else if (error.message) {
                msg = error.message;
            }
            errorMessage.value = msg;
        })
        .finally(() => {
            isSending.value = false;
        });
};

const retrySendMessage = () => {
    if (!lastAttemptedMessage.value) return;
    messageText.value = lastAttemptedMessage.value;
    sendMessage();
};

const startNewSession = () => {
    router.post(
        "/conversations",
        { scenario_id: props.conversation.scenario_id },
        {
            onStart: () => {
                isStarting.value = true;
                errorMessage.value = null;
            },
            onFinish: () => {
                isStarting.value = false;
            },
        },
    );
};

const copiedId = ref(null);

const copyMessage = async (msg) => {
    try {
        await navigator.clipboard.writeText(msg.content);
        copiedId.value = msg.id;
        setTimeout(() => (copiedId.value = null), 2000);
    } catch (e) {
        // clipboard not available
    }
};
</script>

<template>
    <Head :title="conversation.scenario.title" />

    <AuthenticatedLayout>
        <template #title>{{ conversation.scenario.title }}</template>

        <div
            class="flex flex-col overflow-hidden px-5 pb-5 pt-4"
            style="height: calc(100vh - 56px)"
        >
            <div class="card flex min-h-0 flex-1 flex-col overflow-hidden">
                <!-- Chat header -->
                <header
                    class="flex-shrink-0 border-b px-6 py-4"
                    style="
                        background: var(--bg-surface);
                        border-color: var(--border-strong);
                        backdrop-filter: blur(20px);
                    "
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="mb-1 text-[11px] font-bold uppercase tracking-widest"
                                style="color: var(--text-3)"
                            >
                                {{
                                    isCompleted
                                        ? "Session Complete"
                                        : "Active simulation"
                                }}
                            </p>
                            <h2
                                class="text-sm font-bold"
                                style="color: var(--text)"
                            >
                                {{ conversation.scenario.title }}
                            </h2>
                            <p
                                class="mt-1 max-w-lg text-xs leading-relaxed"
                                style="color: var(--text-2)"
                            >
                                {{ conversation.scenario.description }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <!-- End Session button — only show when active -->
                            <button
                                v-if="!isCompleted"
                                @click="showEndConfirm = true"
                                class="rounded-full border px-3 py-1 text-[11px] font-semibold transition-all duration-200 hover:opacity-80"
                                style="
                                    background: var(--red-bg);
                                    color: var(--red);
                                    border-color: rgba(220, 38, 38, 0.2);
                                "
                            >
                                End Session
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Results panel — shown when completed -->
                <div
                    v-if="isCompleted && scores"
                    class="flex-1 overflow-y-auto px-6 py-6"
                >
                    <!-- Overall score -->
                    <div class="mb-6 text-center">
                        <p
                            class="text-[11px] font-bold uppercase tracking-widest mb-2"
                            style="color: var(--text-3)"
                        >
                            Overall Performance
                        </p>
                        <div
                            class="inline-flex items-center justify-center w-24 h-24 rounded-full border-4 mb-3"
                            :style="{
                                borderColor:
                                    scores.final >= 80
                                        ? 'var(--green)'
                                        : scores.final >= 60
                                          ? 'var(--amber)'
                                          : 'var(--red)',
                                background:
                                    scores.final >= 80
                                        ? 'var(--green-bg)'
                                        : scores.final >= 60
                                          ? 'var(--amber-bg)'
                                          : 'var(--red-bg)',
                            }"
                        >
                            <span
                                class="text-3xl font-extrabold"
                                :style="{
                                    color:
                                        scores.final >= 80
                                            ? 'var(--green)'
                                            : scores.final >= 60
                                              ? 'var(--amber)'
                                              : 'var(--red)',
                                }"
                            >
                                {{ scores.final }}
                            </span>
                        </div>
                        <p class="text-sm font-bold" style="color: var(--text)">
                            {{
                                scores.final >= 80
                                    ? "Excellent"
                                    : scores.final >= 60
                                      ? "Good effort"
                                      : "Keep practising"
                            }}
                        </p>
                    </div>

                    <!-- Breakdown scores -->
                    <div
                        class="card p-5 mb-4"
                        style="background: var(--bg-surface2)"
                    >
                        <h3
                            class="text-[11px] font-bold uppercase tracking-widest mb-4"
                            style="color: var(--text-3)"
                        >
                            Performance Breakdown
                        </h3>
                        <div class="flex flex-col gap-4">
                            <div
                                v-for="(label, key) in {
                                    clarity: 'Clarity',
                                    confidence: 'Confidence',
                                    objective: 'Objective',
                                    adaptability: 'Adaptability',
                                }"
                                :key="key"
                            >
                                <div class="flex justify-between mb-1">
                                    <span
                                        class="text-xs font-semibold"
                                        style="color: var(--text-2)"
                                        >{{ label }}</span
                                    >
                                    <span
                                        class="text-xs font-bold"
                                        style="color: var(--text)"
                                        >{{ scores[key] }}/100</span
                                    >
                                </div>
                                <div
                                    class="w-full rounded-full h-2"
                                    style="background: var(--accent-bg)"
                                >
                                    <div
                                        class="h-2 rounded-full transition-all duration-700"
                                        :style="{
                                            width: scores[key] + '%',
                                            background:
                                                'linear-gradient(90deg, #7c3aed, #a855f7)',
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Feedback -->
                    <div
                        class="card p-5 mb-6"
                        style="
                            background: var(--accent-bg);
                            border-color: rgba(124, 58, 237, 0.2);
                        "
                    >
                        <h3
                            class="text-[11px] font-bold uppercase tracking-widest mb-2"
                            style="color: var(--accent)"
                        >
                            Coach Feedback
                        </h3>
                        <p
                            class="text-xs leading-relaxed"
                            style="color: var(--text-2)"
                        >
                            {{ scores.feedback }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 justify-center">
                        <Link
                            href="/scenarios"
                            class="rounded-xl border px-5 py-2.5 text-xs font-semibold transition-all hover:opacity-80 no-underline"
                            style="
                                border-color: var(--border);
                                color: var(--text-2);
                            "
                        >
                            ← Back to Scenarios
                        </Link>
                        <button
                            @click="startNewSession"
                            class="btn-primary rounded-xl px-5 py-2.5 text-xs"
                        >
                            Try Again →
                        </button>

                        <a
                            :href="`/conversations/${conversation.id}/export`"
                            target="_blank"
                            class="rounded-xl border px-5 py-2.5 text-xs font-semibold transition-all hover:opacity-80 no-underline"
                            style="
                                border-color: var(--border);
                                color: var(--text-2);
                            "
                        >
                            ↓ Download Transcript
                        </a>
                    </div>
                </div>

                <!-- Completed but no scores yet -->
                <div
                    v-else-if="isCompleted && !scores"
                    class="flex-1 flex items-center justify-center"
                >
                    <p class="text-sm" style="color: var(--text-2)">
                        Session completed. No evaluation available.
                    </p>
                </div>

                <!-- Active chat messages -->
                <div
                    v-else
                    ref="messagesEl"
                    class="flex flex-1 flex-col gap-3 overflow-y-auto px-6 py-5"
                    style="scroll-behavior: smooth"
                >
                    <!-- Objective banner -->
                    <div
                        class="rounded-xl border p-4 text-xs leading-relaxed"
                        style="
                            background: var(--accent-bg);
                            border-color: rgba(124, 58, 237, 0.2);
                            color: var(--text-2);
                        "
                    >
                        <div
                            class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest"
                            style="color: var(--accent)"
                        >
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <circle cx="12" cy="12" r="6" />
                                <circle cx="12" cy="12" r="2" />
                            </svg>
                            Your objective
                        </div>
                        <p class="m-0">
                            Stay in character and guide the conversation toward
                            a successful outcome. Read each reply carefully
                            before you respond.
                        </p>
                    </div>

                    <!-- Message rows / Skeleton loader -->
                    <template v-if="isStarting">
                        <div
                            class="flex items-end gap-2 justify-start animate-pulse"
                        >
                            <div
                                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border text-[10px] font-bold"
                                style="
                                    background: rgba(124, 58, 237, 0.15);
                                    color: var(--accent);
                                    border-color: rgba(124, 58, 237, 0.2);
                                    opacity: 0.5;
                                "
                                aria-hidden="true"
                            >
                                AI
                            </div>
                            <div
                                class="flex flex-col gap-2.5 max-w-[68%] rounded-[18px] rounded-bl-[4px] border px-4 py-[14px] w-full"
                                style="
                                    background: var(
                                        --msg-ai-bg,
                                        rgba(255, 255, 255, 0.8)
                                    );
                                    border-color: rgba(167, 139, 250, 0.2);
                                    box-shadow: 0 2px 12px
                                        rgba(124, 58, 237, 0.08);
                                "
                            >
                                <div
                                    class="h-3 bg-slate-200 dark:bg-slate-700 rounded-full"
                                    style="width: 60%"
                                ></div>
                                <div
                                    class="h-3 bg-slate-200 dark:bg-slate-700 rounded-full"
                                    style="width: 80%"
                                ></div>
                                <div
                                    class="h-3 bg-slate-200 dark:bg-slate-700 rounded-full"
                                    style="width: 40%"
                                ></div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div
                            v-for="msg in localMessages"
                            :key="msg.id"
                            class="flex items-end gap-2 group"
                            :class="
                                isUser(msg.role)
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <div
                                v-if="!isUser(msg.role)"
                                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border text-[10px] font-bold"
                                style="
                                    background: rgba(124, 58, 237, 0.15);
                                    color: var(--accent);
                                    border-color: rgba(124, 58, 237, 0.2);
                                "
                                aria-hidden="true"
                            >
                                AI
                            </div>

                            <!-- bubble + copy button wrapper -->
                            <div
                                class="relative flex flex-col items-end gap-1 max-w-[68%]"
                            >
                                <div
                                    class="w-full rounded-[18px] px-4 py-[11px] text-[13px] leading-relaxed"
                                    :class="
                                        isUser(msg.role)
                                            ? 'rounded-br-[4px]'
                                            : 'rounded-bl-[4px] border'
                                    "
                                    :style="
                                        isUser(msg.role)
                                            ? 'background: linear-gradient(135deg,#7c3aed,#a855f7); color: white; box-shadow: 0 4px 16px rgba(124,58,237,0.35);'
                                            : 'background: var(--msg-ai-bg, rgba(255,255,255,0.8)); color: var(--msg-ai-text, var(--text)); border-color: var(--border); box-shadow: var(--shadow-sm);'
                                    "
                                >
                                    {{ msg.content }}
                                </div>

                                <!-- copy button — appears on group hover -->
                                <button
                                    @click="copyMessage(msg)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full border"
                                    :style="
                                        isUser(msg.role)
                                            ? 'color: var(--text-3); border-color: var(--border); background: var(--bg-surface);'
                                            : 'color: var(--text-3); border-color: var(--border); background: var(--bg-surface);'
                                    "
                                    :aria-label="
                                        copiedId === msg.id
                                            ? 'Copied'
                                            : 'Copy message'
                                    "
                                >
                                    <!-- checkmark when copied -->
                                    <svg
                                        v-if="copiedId === msg.id"
                                        width="11"
                                        height="11"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        style="color: var(--green)"
                                    >
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <!-- copy icon -->
                                    <svg
                                        v-else
                                        width="11"
                                        height="11"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect
                                            x="9"
                                            y="9"
                                            width="13"
                                            height="13"
                                            rx="2"
                                            ry="2"
                                        />
                                        <path
                                            d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"
                                        />
                                    </svg>
                                    <span>{{
                                        copiedId === msg.id ? "Copied" : "Copy"
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Typing indicator -->
                    <div v-if="processing" class="flex items-end gap-2">
                        <div
                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border text-[10px] font-bold"
                            style="
                                background: rgba(124, 58, 237, 0.15);
                                color: var(--accent);
                                border-color: rgba(124, 58, 237, 0.2);
                            "
                            aria-hidden="true"
                        >
                            AI
                        </div>
                        <div
                            class="flex items-center gap-[5px] rounded-[18px] rounded-bl-[4px] border px-[18px] py-[14px]"
                            style="
                                background: var(
                                    --msg-ai-bg,
                                    rgba(255, 255, 255, 0.8)
                                );
                                border-color: var(--border);
                            "
                        >
                            <span
                                class="typing-dot h-[7px] w-[7px] rounded-full"
                                style="background: rgba(124, 58, 237, 0.4)"
                            />
                            <span
                                class="typing-dot h-[7px] w-[7px] rounded-full"
                                style="background: rgba(124, 58, 237, 0.4)"
                            />
                            <span
                                class="typing-dot h-[7px] w-[7px] rounded-full"
                                style="background: rgba(124, 58, 237, 0.4)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Footer — hidden when completed -->
                <footer
                    v-if="!isCompleted"
                    class="flex-shrink-0 border-t px-5 py-3"
                    style="
                        background: var(--bg-surface);
                        border-color: var(--border-strong);
                        backdrop-filter: blur(20px);
                    "
                >
                    <!-- Error Banner -->
                    <div
                        v-if="errorMessage"
                        class="mb-3 flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-xs transition-all duration-200"
                        style="
                            background: rgba(239, 68, 68, 0.1);
                            border-color: rgba(239, 68, 68, 0.2);
                            color: #ef4444;
                        "
                    >
                        <div class="flex items-center gap-2">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            <span>{{ errorMessage }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="retrySendMessage"
                                class="font-bold underline hover:opacity-80 transition-opacity"
                                style="
                                    background: transparent;
                                    border: 0;
                                    color: inherit;
                                    padding: 0;
                                    cursor: pointer;
                                "
                            >
                                Retry
                            </button>
                            <button
                                type="button"
                                @click="errorMessage = null"
                                class="hover:opacity-80 transition-opacity"
                                style="
                                    background: transparent;
                                    border: 0;
                                    color: inherit;
                                    padding: 0;
                                    cursor: pointer;
                                "
                                aria-label="Dismiss"
                            >
                                <svg
                                    width="14"
                                    height="14"
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
                        </div>
                    </div>

                    <form
                        class="chat-form flex items-center gap-2 rounded-full border px-[18px] py-[6px] pr-[6px] backdrop-blur"
                        style="
                            background: var(--bg-surface2);
                            border-color: rgba(167, 139, 250, 0.25);
                            box-shadow: var(--shadow-sm);
                        "
                        @submit.prevent="sendMessage"
                    >
                        <input
                            ref="inputRef"
                            v-model="messageText"
                            type="text"
                            class="flex-1 border-0 bg-transparent text-[13px] outline-none focus:outline-none focus:ring-0"
                            style="font-family: inherit; color: var(--text)"
                            :placeholder="
                                isListening
                                    ? 'Listening...'
                                    : `Reply as yourself in ${conversation.scenario.title}…`
                            "
                            :disabled="isSending || processing"
                            autocomplete="off"
                        />

                        <p
                            v-if="userMessageCount >= 9"
                            class="text-[10px] text-center mt-1.5"
                            style="color: var(--amber)"
                        >
                            Next message will complete the session
                        </p>

                        <!-- Mic button -->
                        <button
                            type="button"
                            @click="toggleVoice"
                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-0 transition-all duration-200"
                            :style="
                                isListening
                                    ? 'background: linear-gradient(135deg,#dc2626,#ef4444); box-shadow: 0 4px 12px rgba(220,38,38,0.4);'
                                    : 'background: var(--bg-surface2); box-shadow: var(--shadow-sm);'
                            "
                            :disabled="isSending || processing"
                            :aria-label="
                                isListening
                                    ? 'Stop recording'
                                    : 'Start voice input'
                            "
                        >
                            <svg
                                v-if="!isListening"
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                style="color: var(--accent)"
                            >
                                <path
                                    d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"
                                />
                                <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                                <line x1="12" y1="19" x2="12" y2="23" />
                                <line x1="8" y1="23" x2="16" y2="23" />
                            </svg>
                            <span
                                v-else
                                class="live-dot h-3 w-3 rounded-full bg-white"
                            />
                        </button>

                        <!-- Message counter -->
                        <span
                            class="text-[10px] font-semibold flex-shrink-0 px-2"
                            :style="{
                                color:
                                    userMessageCount >= 10
                                        ? 'var(--red)'
                                        : userMessageCount >= 8
                                          ? 'var(--amber)'
                                          : 'var(--text-3)',
                            }"
                        >
                            {{ userMessageCount }}/10
                        </span>

                        <!-- Inline Spinner -->
                        <div
                            v-if="isSending"
                            class="flex items-center justify-center mr-1"
                            aria-label="Sending..."
                        >
                            <svg
                                class="animate-spin h-5 w-5 text-purple-600"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="3"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                        </div>

                        <!-- Send button -->
                        <button
                            type="submit"
                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-0 transition-all duration-200"
                            style="
                                background: linear-gradient(
                                    135deg,
                                    #7c3aed,
                                    #a855f7
                                );
                                box-shadow: var(--shadow-btn);
                            "
                            :disabled="
                                isSending || processing || !messageText.trim()
                            "
                            :class="
                                isSending || processing || !messageText.trim()
                                    ? 'opacity-35 cursor-not-allowed'
                                    : 'hover:opacity-90 hover:scale-105'
                            "
                            aria-label="Send message"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="white"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="22" y1="2" x2="11" y2="13" />
                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                            </svg>
                        </button>
                    </form>
                </footer>
            </div>
        </div>

        <!-- End Session confirmation modal -->
        <div
            v-if="showEndConfirm"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="showEndConfirm = false"
        >
            <div
                class="card w-full max-w-sm p-6 flex flex-col gap-4"
                style="background: var(--bg-surface)"
            >
                <h2 class="text-base font-bold" style="color: var(--text)">
                    End this session?
                </h2>
                <p class="text-xs leading-relaxed" style="color: var(--text-2)">
                    Your conversation will be evaluated and you'll receive a
                    detailed performance score. This cannot be undone.
                </p>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="showEndConfirm = false"
                        class="px-4 py-2 text-xs font-medium rounded-xl border transition-all hover:opacity-80"
                        style="
                            border-color: var(--border);
                            color: var(--text-2);
                            background: transparent;
                        "
                    >
                        Cancel
                    </button>
                    <button
                        @click="endSession"
                        class="px-4 py-2 text-xs font-semibold rounded-xl border-0 text-white transition-all hover:opacity-80"
                        style="
                            background: linear-gradient(
                                135deg,
                                #dc2626,
                                #ef4444
                            );
                        "
                    >
                        End & Evaluate
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.chat-form:focus-within {
    border-color: rgba(124, 58, 237, 0.45) !important;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.08) !important;
}
</style>
