<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use Filament\Widgets\ChartWidget;

class SupportTicketsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Support Tickets by Status';

    protected ?string $description = 'Current support queue distribution.';

    protected static ?int $sort = 1;

    protected ?string $maxHeight = '300px';

    protected string|int|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = SupportTicket::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'datasets' => [
                [
                    'data' => collect(array_keys(SupportTicket::statuses()))
                        ->map(fn (string $status): int => (int) ($counts[$status] ?? 0))
                        ->all(),
                    'backgroundColor' => [
                        '#6366f1',
                        '#3b82f6',
                        '#f59e0b',
                        '#22c55e',
                        '#64748b',
                    ],
                ],
            ],
            'labels' => array_values(SupportTicket::statuses()),
        ];
    }
}
