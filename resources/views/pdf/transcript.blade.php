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

            /* solid fill — DomPDF does not support linear-gradient on background */
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
            <table style="width:100%;background:#f0f9f8;border-bottom:2px solid #d0eeeb;border-collapse:collapse;">
                <tr>
                    <td style="padding:24px 0 24px 40px;width:96px;vertical-align:middle;">
                        {{-- SVG circle: DomPDF renders this perfectly, unlike border-radius:50% on divs --}}
                        <svg width="80" height="80" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="40" cy="40" r="36" fill="#f0f9f8" stroke="#0f7c6e"
                                stroke-width="3" />
                            <text x="40" y="46" text-anchor="middle" font-family="DejaVu Sans, sans-serif"
                                font-size="22" font-weight="bold" fill="#0f7c6e">{{ $scores['final'] }}</text>
                        </svg>
                    </td>
                    <td style="padding:24px 40px 24px 12px;vertical-align:middle;">
                        <div
                            style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:.1em;color:#14a896;margin-bottom:4px;">
                            Overall Performance
                        </div>
                        <div style="font-size:16px;font-weight:bold;color:#0d1f2d;margin-bottom:12px;">
                            @if ($scores['final'] >= 80)
                                Excellent
                            @elseif($scores['final'] >= 60)
                                Good Effort
                            @else
                                Keep Practising
                            @endif
                        </div>
                        <table style="border-collapse:collapse;">
                            <tr>
                                @foreach (['clarity' => 'Clarity', 'confidence' => 'Confidence', 'objective' => 'Objective', 'adaptability' => 'Adaptability'] as $key => $label)
                                    <td style="text-align:center;padding-right:20px;">
                                        <div style="font-size:14px;font-weight:bold;color:#0f7c6e;">
                                            {{ $scores[$key] ?? '—' }}</div>
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
                    <div class="breakdown-row">
                        <div class="breakdown-top">
                            <span class="breakdown-name">{{ $label }}</span>
                            <span class="breakdown-score">{{ $scores[$key] ?? '—' }}/100</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:{{ $scores[$key] ?? 0 }}%;"></div>
                        </div>
                    </div>
                @endforeach

                <div class="divider"></div>

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
