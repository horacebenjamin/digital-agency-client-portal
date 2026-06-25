<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProjectFiles\ProjectFileResource;
use App\Filament\Resources\ProjectUpdates\ProjectUpdateResource;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class AdminRecentActivity extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Activity')
            ->description('Latest support tickets, project updates, and uploaded files.')
            ->records(fn (): array => $this->getRecentActivity())
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Activity')
                    ->weight('medium')
                    ->width('22rem')
                    ->grow(false)
                    ->formatStateUsing(fn (?string $state, array $record): HtmlString|string|null => filled($state)
                        ? $this->activityTitle($state, $record['type'])
                        : $state)
                    ->tooltip(fn (array $record): string => $record['title'])
                    ->extraCellAttributes($this->tableCellAttributes()),
                TextColumn::make('context')
                    ->label('Client')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No client')
                    ->formatStateUsing(fn (?string $state): HtmlString|string|null => filled($state)
                        ? new HtmlString('<span style="display: inline-block; padding-left: .2rem; padding-right: .2rem;">'.e(str($state)->limit(28)->toString()).'</span>')
                        : $state)
                    ->limit(28)
                    ->tooltip(fn (array $record): ?string => $record['context'])
                    ->extraCellAttributes($this->tableCellAttributes()),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Support Ticket' => 'warning',
                        'Project Update' => 'success',
                        'Project File' => 'info',
                        default => 'gray',
                    })
                    ->extraCellAttributes($this->tableCellAttributes()),
                TextColumn::make('date')
                    ->label('Date / Time')
                    ->placeholder('Not available')
                    ->formatStateUsing(fn (?string $state, array $record): HtmlString|string|null => filled($state)
                        ? $this->activityDate($record['date'], $record['time'])
                        : $state)
                    ->extraCellAttributes($this->tableCellAttributes()),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->button()
                    ->outlined()
                    ->size(Size::Small)
                    ->icon(Heroicon::OutlinedEye)
                    ->extraAttributes([
                        'style' => 'border-radius: .5rem; padding-left: .75rem; padding-right: .75rem;',
                    ])
                    ->url(fn (array $record): string => $record['url']),
            ])
            ->emptyStateHeading('No recent portal activity')
            ->emptyStateDescription('Recent support tickets, project updates, and uploaded files will appear here.');
    }

    /**
     * @return array<int, array{type: string, title: string, context: string|null, date: string|null, time: string|null, url: string, sort_date: mixed}>
     */
    public function getRecentActivity(): array
    {
        return collect()
            ->merge($this->recentSupportTickets())
            ->merge($this->recentProjectUpdates())
            ->merge($this->recentProjectFiles())
            ->sortByDesc('sort_date')
            ->take(10)
            ->values()
            ->all();
    }

    private function recentSupportTickets(): Collection
    {
        return SupportTicket::query()
            ->with('project.client:id,company_name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'type' => 'Support Ticket',
                'title' => $ticket->title,
                'context' => $ticket->project?->client?->company_name,
                'date' => $ticket->created_at?->format('d M Y'),
                'time' => $ticket->created_at?->format('g:i A'),
                'sort_date' => $ticket->created_at,
                'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
            ]);
    }

    private function recentProjectUpdates(): Collection
    {
        return ProjectUpdate::query()
            ->with('project.client:id,company_name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectUpdate $update): array => [
                'type' => 'Project Update',
                'title' => $update->title,
                'context' => $update->project?->client?->company_name,
                'date' => $update->created_at?->format('d M Y'),
                'time' => $update->created_at?->format('g:i A'),
                'sort_date' => $update->created_at,
                'url' => ProjectUpdateResource::getUrl('edit', ['record' => $update]),
            ]);
    }

    private function recentProjectFiles(): Collection
    {
        return ProjectFile::query()
            ->with('project.client:id,company_name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProjectFile $file): array => [
                'type' => 'Project File',
                'title' => $file->name,
                'context' => $file->project?->client?->company_name,
                'date' => $file->created_at?->format('d M Y'),
                'time' => $file->created_at?->format('g:i A'),
                'sort_date' => $file->created_at,
                'url' => ProjectFileResource::getUrl('edit', ['record' => $file]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function tableCellAttributes(): array
    {
        return [
            'style' => 'padding-top: .9rem; padding-bottom: .9rem; vertical-align: middle;',
        ];
    }

    private function activityTitle(string $title, string $type): HtmlString
    {
        return new HtmlString(
            '<div style="max-width: 22rem;">'
                .'<div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600;">'.e($title).'</div>'
                .'<div style="margin-top: .2rem; font-size: .75rem; line-height: 1rem; color: rgb(107 114 128);">'.e($type).'</div>'
            .'</div>'
        );
    }

    private function activityDate(string $date, ?string $time): HtmlString
    {
        return new HtmlString(
            '<div style="line-height: 1.25rem;">'
                .'<div>'.e($date).'</div>'
                .'<div style="font-size: .75rem; color: rgb(107 114 128);">'.e($time).'</div>'
            .'</div>'
        );
    }
}
