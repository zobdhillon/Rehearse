<!DOCTYPE html>
<html>

    <head>
        <meta charset="utf-8">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 11px;
                color: #1a1a2e;
                background: #ffffff;
            }

            /* ── Header band ── */
            .header {
                background: #7c3aed;
                padding: 32px 40px 28px;
                color: white;
            }

            .header-label {
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .12em;
                opacity: .7;
                margin-bottom: 6px;
            }

            .header-title {
                font-size: 22px;
                font-weight: bold;
                margin-bottom: 6px;
                line-height: 1.2;
            }

            .header-meta {
                font-size: 10px;
                opacity: .65;
            }

            /* ── Score hero ── */
            .score-hero {
                background: #f5f0ff;
                padding: 24px 40px;
                display: flex;
                align-items: center;
                gap: 32px;
                border-bottom: 2px solid #ede9fe;
            }

            .score-circle {
                width: 72px;
                height: 72px;
                border-radius: 50%;
                border: 3px solid #7c3aed;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .score-circle-num {
                font-size: 24px;
                font-weight: bold;
                color: #7c3aed;
            }

            .score-label-main {
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .1em;
                color: #9f7aea;
                margin-bottom: 4px;
            }

            .score-verdict {
                font-size: 16px;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom: 10px;
            }

            .score-dims {
                display: flex;
                gap: 16px;
            }

            .score-dim {
                text-align: center;
            }

            .score-dim-val {
                font-size: 14px;
                font-weight: bold;
                color: #7c3aed;
            }

            .score-dim-name {
                font-size: 9px;
                color: #888;
                text-transform: uppercase;
                letter-spacing: .06em;
                margin-top: 1px;
            }

            /* ── Body ── */
            .body {
                padding: 28px 40px;
            }

            /* ── Section header ── */
            .section-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 14px;
                margin-top: 28px;
            }

            .section-header:first-child {
                margin-top: 0;
            }

            .section-dot {
                width: 4px;
                height: 16px;
                background: #7c3aed;
                border-radius: 2px;
                flex-shrink: 0;
            }

            .section-title {
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .1em;
                color: #7c3aed;
            }

            /* ── Messages ── */
            .message {
                margin-bottom: 14px;
                padding-left: 12px;
            }

            .message-role {
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .08em;
                margin-bottom: 3px;
                color: #aaa;
            }

            .message.user .message-role {
                color: #7c3aed;
            }

            .message-bubble {
                background: #f8f8fb;
                border-left: 2px solid #e5e7eb;
                border-radius: 0 6px 6px 0;
                padding: 8px 12px;
                line-height: 1.65;
                color: #374151;
            }

            .message.user .message-bubble {
                background: #f5f0ff;
                border-left-color: #7c3aed;
                color: #1a1a2e;
            }

            /* ── Breakdown bars ── */
            .breakdown-row {
                margin-bottom: 12px;
            }

            .breakdown-top {
                display: flex;
                justify-content: space-between;
                margin-bottom: 4px;
            }

            .breakdown-name {
                font-size: 10px;
                font-weight: bold;
                color: #374151;
            }

            .breakdown-score {
                font-size: 10px;
                font-weight: bold;
                color: #7c3aed;
            }

            .bar-track {
                height: 6px;
                background: #ede9fe;
                border-radius: 3px;
            }

            .bar-fill {
                height: 6px;
                border-radius: 3px;
                background: #7c3aed;
            }

            /* ── Feedback box ── */
            .feedback-box {
                background: #f5f0ff;
                border: 1px solid #ddd6fe;
                border-radius: 8px;
                padding: 16px 18px;
            }

            .feedback-text {
                line-height: 1.7;
                color: #4c3d8f;
                font-size: 11px;
            }

            /* ── Footer ── */
            .footer {
                margin-top: 36px;
                padding-top: 14px;
                border-top: 1px solid #f0f0f0;
                display: flex;
                justify-content: space-between;
                font-size: 9px;
                color: #bbb;
            }

            /* ── Divider ── */
            .divider {
                height: 1px;
                background: #f3f4f6;
                margin: 20px 0;
            }
        </style>
    </head>

    <body>

        {{-- Header --}}
        <div class="header">
            <div class="header-label">Session Report</div>
            <div class="header-title">{{ $scenario->title }}</div>
            <div class="header-meta">
                Session #{{ $conversation->id }} &nbsp;·&nbsp;
                {{ $conversation->created_at->format('F j, Y \a\t g:ia') }}
            </div>
        </div>

        {{-- Score hero --}}
        @if ($scores)
            <table
                style="width: 100%; background: #f5f0ff; border-bottom: 2px solid #ede9fe; border-collapse: collapse;">
                <tr>
                    <td style="padding: 24px 0 24px 40px; width: 90px; vertical-align: middle;">
                        <div
                            style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid #7c3aed; text-align: center; padding-top: 18px;">
                            <span
                                style="font-size: 22px; font-weight: bold; color: #7c3aed;">{{ $scores['final'] }}</span>
                        </div>
                    </td>
                    <td style="padding: 24px 40px 24px 16px; vertical-align: middle;">
                        <div
                            style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .1em; color: #9f7aea; margin-bottom: 4px;">
                            Overall Performance</div>
                        <div style="font-size: 16px; font-weight: bold; color: #1a1a2e; margin-bottom: 10px;">
                            @if ($scores['final'] >= 80)
                                Excellent
                            @elseif($scores['final'] >= 60)
                                Good Effort
                            @else
                                Keep Practising
                            @endif
                        </div>
                        <table style="border-collapse: collapse;">
                            <tr>
                                @foreach (['clarity' => 'Clarity', 'confidence' => 'Confidence', 'objective' => 'Objective', 'adaptability' => 'Adaptability'] as $key => $label)
                                    <td style="text-align: center; padding-right: 20px;">
                                        <div style="font-size: 14px; font-weight: bold; color: #7c3aed;">
                                            {{ $scores[$key] ?? '—' }}</div>
                                        <div
                                            style="font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: .06em;">
                                            {{ $label }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endif

        <div class="body">

            {{-- Breakdown bars --}}
            @if ($scores)
                <div class="section-header">
                    <div class="section-dot"></div>
                    <div class="section-title">Performance Breakdown</div>
                </div>
                @foreach (['clarity' => 'Clarity', 'confidence' => 'Confidence', 'objective' => 'Objective', 'adaptability' => 'Adaptability'] as $key => $label)
                    <div class="breakdown-row">
                        <div class="breakdown-top">
                            <span class="breakdown-name">{{ $label }}</span>
                            <span class="breakdown-score">{{ $scores[$key] ?? '—' }}/100</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $scores[$key] ?? 0 }}%"></div>
                        </div>
                    </div>
                @endforeach

                <div class="divider"></div>

                {{-- Feedback --}}
                @if (!empty($scores['feedback']))
                    <div class="section-header">
                        <div class="section-dot"></div>
                        <div class="section-title">Coach Feedback</div>
                    </div>
                    <div class="feedback-box">
                        <p class="feedback-text">{{ $scores['feedback'] }}</p>
                    </div>
                    <div class="divider"></div>
                @endif
            @endif

            {{-- Transcript --}}
            <div class="section-header">
                <div class="section-dot"></div>
                <div class="section-title">Conversation Transcript</div>
            </div>
            @foreach ($messages as $msg)
                @if ($msg->role !== 'system')
                    <div class="message {{ $msg->role }}">
                        <div class="message-role">
                            {{ $msg->role === 'user' ? 'You' : 'Interviewer' }}
                        </div>
                        <div class="message-bubble">{{ $msg->content }}</div>
                    </div>
                @endif
            @endforeach

            {{-- Footer --}}
            <div class="footer">
                <span>{{ $scenario->title }} — Session #{{ $conversation->id }}</span>
                <span>Generated {{ now()->format('F j, Y') }}</span>
            </div>

        </div>
    </body>

</html>
