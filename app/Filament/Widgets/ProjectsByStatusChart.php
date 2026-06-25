<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;

class ProjectsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Projects by Status';

    protected ?string $description = 'Current distribution of project work.';

    protected static ?int $sort = 0;

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
        $counts = Project::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statuses = [
            'planning' => 'Planning',
            'in_progress' => 'In Progress',
            'on_hold' => 'On Hold',
            'completed' => 'Completed',
        ];

        return [
            'datasets' => [
                [
                    'data' => collect(array_keys($statuses))
                        ->map(fn (string $status): int => (int) ($counts[$status] ?? 0))
                        ->all(),
                    'backgroundColor' => [
                        '#94a3b8',
                        '#3b82f6',
                        '#f59e0b',
                        '#22c55e',
                    ],
                ],
            ],
            'labels' => array_values($statuses),
        ];
    }
}
