<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} - {{ $paymentRequest->title }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .page {
            padding: 42px;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 28px;
            padding-bottom: 18px;
        }

        .portal {
            color: #4b5563;
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .contact {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        h1 {
            font-size: 28px;
            line-height: 1.2;
            margin: 8px 0 0;
        }

        h2 {
            font-size: 15px;
            margin: 0 0 10px;
        }

        .summary {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            margin-bottom: 24px;
            padding: 18px;
        }

        .amount {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .status {
            color: #374151;
            margin: 4px 0 0;
        }

        .reference {
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 10px;
        }

        .grid {
            display: table;
            margin-bottom: 24px;
            width: 100%;
        }

        .column {
            display: table-cell;
            padding-right: 18px;
            vertical-align: top;
            width: 50%;
        }

        .panel {
            border: 1px solid #e5e7eb;
            padding: 16px;
        }

        dl {
            margin: 0;
        }

        dt {
            color: #6b7280;
            font-size: 11px;
            margin-top: 12px;
            text-transform: uppercase;
        }

        dt:first-child {
            margin-top: 0;
        }

        dd {
            margin: 3px 0 0;
        }

        .description {
            border-top: 1px solid #e5e7eb;
            margin-top: 18px;
            padding-top: 18px;
            white-space: pre-line;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 11px;
            margin-top: 32px;
            padding-top: 14px;
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <div class="portal">{{ $portalName }}</div>
            <div class="contact">{{ $agencyEmail }}</div>
            <h1>{{ $documentTitle }}</h1>
        </header>

        <section class="summary">
            <p class="reference">{{ $documentReference }}</p>
            <p class="amount">{{ $amount }}</p>
            <p class="status">Status: {{ $status }}</p>
        </section>

        <section class="grid">
            <div class="column">
                <div class="panel">
                    <h2>Client</h2>
                    <dl>
                        <dt>Name</dt>
                        <dd>{{ $clientName }}</dd>

                        <dt>Project</dt>
                        <dd>{{ $projectName ?? 'No linked project' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="column">
                <div class="panel">
                    <h2>Payment Details</h2>
                    <dl>
                        <dt>Title</dt>
                        <dd>{{ $paymentRequest->title }}</dd>

                        <dt>Currency</dt>
                        <dd>{{ $currency }}</dd>

                        <dt>Due Date</dt>
                        <dd>{{ $dueDate ?? 'No due date' }}</dd>

                        <dt>Paid Date</dt>
                        <dd>{{ $paidDate ?? 'Not paid yet' }}</dd>

                        <dt>Stripe Payment ID</dt>
                        <dd>{{ $stripePaymentId ?? 'Not available' }}</dd>
                    </dl>
                </div>
            </div>
        </section>

        @if (filled($paymentRequest->description))
            <section class="description">
                <h2>Description</h2>
                <p>{{ $paymentRequest->description }}</p>
            </section>
        @endif

        <footer class="footer">
            Generated {{ $generatedDate }} by {{ $portalName }}. Contact {{ $agencyEmail }} with any questions.
        </footer>
    </main>
</body>
</html>
