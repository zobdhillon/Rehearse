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
                color: #0d1f2d;
                background: #ffffff;
            }

            .header {
                background: #0f7c6e;
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

            .body {
                padding: 28px 40px;
            }

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
                background: #0f7c6e;
                border-radius: 2px;
                flex-shrink: 0;
            }

            .section-title {
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .1em;
                color: #0f7c6e;
            }

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
                color: #0f7c6e;
            }

            .message-bubble {
                background: #f7f9fb;
                border-left: 2px solid #e0e7ef;
                border-radius: 0 6px 6px 0;
                padding: 8px 12px;
                line-height: 1.65;
                color: #3d5166;
            }

            .message.user .message-bubble {
                background: #f0f9f8;
                border-left-color: #0f7c6e;
                color: #0d1f2d;
            }

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
                color: #3d5166;
            }

            .breakdown-score {
                font-size: 10px;
                font-weight: bold;
                color: #0f7c6e;
            }

            .bar-track {
                height: 7px;
                background: #d0eeeb;
                border-radius: 4px;
                overflow: hidden;
            }

            .bar-fill {
                height: 7px;
                background: #0f7c6e;
            }

            .feedback-box {
                background: #f0f9f8;
                border: 1px solid #d0eeeb;
                border-radius: 8px;
                padding: 16px 18px;
            }

            .feedback-text {
                line-height: 1.7;
                color: #0d4a42;
                font-size: 11px;
            }

            .footer {
                margin-top: 36px;
                padding-top: 14px;
                border-top: 1px solid #e8eef3;
                display: flex;
                justify-content: space-between;
                font-size: 9px;
                color: #6b8299;
            }

            .divider {
                height: 1px;
                background: #e8eef3;
                margin: 20px 0;
            }

            /* NEW: Pulse animation for low score ring */
            @keyframes pulse {
                0% {
                    opacity: 0.3;
                    transform: scale(1);
                }

                50% {
                    opacity: 0.8;
                    transform: scale(1.05);
                }

                100% {
                    opacity: 0.3;
                    transform: scale(1);
                }
            }

            .pulse-ring {
                animation: pulse 2s ease-in-out infinite;
            }

            /* NEW: Warning badge for low scores */
            .score-warning {
                display: inline-block;
                background: #d9534f;
                color: white;
                font-size: 8px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .06em;
                padding: 2px 8px;
                border-radius: 99px;
                margin-left: 8px;
            }
        </style>
    </head>

    <body>

        {{-- Header --}}
        <div class="header">
            <div class="header-label">Session Report · Rehearse AI Coach</div>
            <div class="header-title">{{ $scenario->title }}</div>
            <div class="header-meta">
                Session #{{ $conversation->id }} &nbsp;·&nbsp;
                {{ $conversation->created_at->format('F j, Y \a\t g:ia') }}
            </div>
        </div>

        {{-- Score hero --}}
        @if ($scores)
            @php
                $finalScore = $scores['final'];
                $isLowScore = $finalScore < 40;
                $scoreColor = $isLowScore ? '#d9534f' : '#0f7c6e';
                $innerFill = $isLowScore ? '#fde8e8' : '#e6f5f3';
                $outerStroke = $isLowScore ? '#d9534f' : '#0f7c6e';
                $performanceLabel =
                    $finalScore >= 80 ? 'Excellent' : ($finalScore >= 60 ? 'Good Effort' : 'Keep Practising');
            @endphp

            <table
                style="width:100%;background:{{ $isLowScore ? '#fdf0ed' : '#f0f9f8' }};border-bottom:2px solid {{ $isLowScore ? '#f5c6c2' : '#d0eeeb' }};border-collapse:collapse;">
                <tr>
                    <td style="padding:24px 0 24px 40px;width:90px;vertical-align:middle;">

                        <svg width="76" height="76" viewBox="0 0 76 76" xmlns="http://www.w3.org/2000/svg">
                            <!-- Outer ring -->
                            <circle cx="38" cy="38" r="35" fill="#ffffff" stroke="{{ $outerStroke }}"
                                stroke-width="3.5" />

                            <!-- Inner fill -->
                            <circle cx="38" cy="38" r="31" fill="{{ $innerFill }}" stroke="none" />

                            <!-- NEW: Pulse ring for low scores -->
                            @if ($isLowScore)
                                <circle cx="38" cy="38" r="35" fill="none" stroke="#d9534f"
                                    stroke-width="1.5" stroke-dasharray="8 4" class="pulse-ring" />
                            @endif

                            <!-- Score text -->
                            <text x="38" y="38" dy="8" text-anchor="middle"
                                font-family="DejaVu Sans, sans-serif" font-size="24" font-weight="bold"
                                fill="{{ $scoreColor }}">{{ $finalScore }}</text>

                            <!-- NEW: Exclamation mark for low scores -->
                            @if ($isLowScore)
                                <text x="52" y="28" dy="0" text-anchor="middle"
                                    font-family="DejaVu Sans, sans-serif" font-size="12" font-weight="bold"
                                    fill="#d9534f">!</text>
                            @endif
                        </svg>
                    </td>
                    <td style="padding:24px 40px 24px 12px;vertical-align:middle;">
                        <div
                            style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:.1em;color:{{ $isLowScore ? '#d9534f' : '#14a896' }};margin-bottom:4px;">
                            Overall Performance
                            @if ($isLowScore)
                                <span class="score-warning">Needs Improvement</span>
                            @endif
                        </div>
                        <div style="font-size:16px;font-weight:bold;color:#0d1f2d;margin-bottom:12px;">
                            {{ $performanceLabel }}
                            @if ($isLowScore)
                                <span style="font-size:12px;font-weight:normal;color:#d9534f;margin-left:8px;">⚠️ Below
                                    target</span>
                            @endif
                        </div>
                        <table style="border-collapse:collapse;">
                            <tr>
                                @foreach (['clarity' => 'Clarity', 'confidence' => 'Confidence', 'objective' => 'Objective', 'adaptability' => 'Adaptability'] as $key => $label)
                                    @php
                                        $subScore = $scores[$key] ?? 0;
                                        $isSubLow = $subScore < 40;
                                    @endphp
                                    <td style="text-align:center;padding-right:20px;">
                                        <div
                                            style="font-size:14px;font-weight:bold;color:{{ $isSubLow ? '#d9534f' : '#0f7c6e' }};">
                                            {{ $subScore }}</div>
                                        <div
                                            style="font-size:9px;color:#6b8299;text-transform:uppercase;letter-spacing:.06em;margin-top:2px;">
                                            {{ $label }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                        @if (!empty($scores['completion_rate']) && $scores['completion_rate'] < 100)
                            <div
                                style="display:inline-block;margin-top:10px;background:#d0eeeb;color:#0f7c6e;font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;padding:3px 10px;border-radius:99px;">
                                {{ $scores['completion_rate'] }}% session completed
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        @endif

        <div class="body">

            @if ($scores)
                <div class="section-header">
                    <div class="section-dot"></div>
                    <div class="section-title">Performance Breakdown</div>
                </div>
                @foreach (['clarity' => 'Clarity', 'confidence' => 'Confidence', 'objective' => 'Objective', 'adaptability' => 'Adaptability'] as $key => $label)
                    @php
                        $subScore = $scores[$key] ?? 0;
                        $isSubLow = $subScore < 40;
                        $barColor = $isSubLow ? '#d9534f' : '#0f7c6e';
                        $scoreColor = $isSubLow ? '#d9534f' : '#0f7c6e';
                    @endphp
                    <div class="breakdown-row">
                        <div class="breakdown-top">
                            <span class="breakdown-name">
                                {{ $label }}
                                @if ($isSubLow)
                                    <span style="color:#d9534f;font-size:8px;margin-left:4px;">⚠️</span>
                                @endif
                            </span>
                            <span class="breakdown-score"
                                style="color:{{ $scoreColor }};">{{ $subScore }}/100</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:{{ $subScore }}%;background:{{ $barColor }};">
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="divider"></div>

                @if (!empty($scores['feedback']))
                    <div class="section-header">
                        <div class="section-dot"></div>
                        <div class="section-title">Coach Feedback</div>
                    </div>
                    <div class="feedback-box"
                        style="{{ $isLowScore ? 'border-color:#f5c6c2;background:#fdf0ed;' : '' }}">
                        <p class="feedback-text">{{ $scores['feedback'] }}</p>
                        @if ($isLowScore)
                            <p style="margin-top:8px;color:#d9534f;font-weight:bold;font-size:10px;">
                                💡 Recommendation: Consider reviewing the fundamentals and practicing with our guided
                                modules.
                            </p>
                        @endif
                    </div>
                    <div class="divider"></div>
                @endif
            @endif

            <div class="section-header">
                <div class="section-dot"></div>
                <div class="section-title">Conversation Transcript</div>
            </div>

            @foreach ($messages as $msg)
                @if ($msg->role !== 'system')
                    <div class="message {{ $msg->role }}">
                        <div class="message-role">{{ $msg->role === 'user' ? 'You' : 'Interviewer' }}</div>
                        <div class="message-bubble">{{ $msg->content }}</div>
                    </div>
                @endif
            @endforeach

            <div class="footer">
                <span>{{ $scenario->title }} · Session #{{ $conversation->id }}</span>
                <span>Rehearse AI Coach · Generated {{ now()->format('F j, Y') }}</span>
            </div>

        </div>
    </body>

</html>
