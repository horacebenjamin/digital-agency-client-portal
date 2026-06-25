<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Portal Overview';

    protected ?string $description = 'Operational pulse across clients, project delivery, support, and shared assets.';

    protected static ?int $sort = 2;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Total Clients', $this->statValue(Client::query()->count()))
                ->description('Client accounts under management')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray')
                ->extraAttributes($this->statCardAttributes()),
            Stat::make('Active Projects', $this->statValue(Project::query()->where('status', '!=', 'completed')->count()))
                ->description('Projects currently moving through delivery')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedBriefcase)
                ->color('info')
                ->extraAttributes($this->statCardAttributes()),
            Stat::make('Open Support Tickets', $this->statValue(SupportTicket::query()
                ->whereNotIn('status', [
                    SupportTicket::STATUS_RESOLVED,
                    SupportTicket::STATUS_CLOSED,
                ])
                ->count()))
                ->description('Client support requests still open')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedTicket)
                ->color('warning')
                ->extraAttributes($this->statCardAttributes()),
            Stat::make('Overdue Projects', $this->statValue(Project::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->where('status', '!=', 'completed')
                ->count()))
                ->description('Active projects beyond their due date')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->extraAttributes($this->statCardAttributes()),
            Stat::make('Published Project Updates', $this->statValue(ProjectUpdate::query()->where('status', 'published')->count()))
                ->description('Client-visible delivery updates')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('success')
                ->extraAttributes($this->statCardAttributes()),
            Stat::make('Uploaded Project Files', $this->statValue(ProjectFile::query()->count()))
                ->description('Assets and documents available to clients')
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedFolderOpen)
                ->color('gray')
                ->extraAttributes($this->statCardAttributes()),
        ];
    }

    private function statValue(int $value): HtmlString
    {
        return new HtmlString('<span style="font-size: 2rem; line-height: 1; font-weight: 700;">'.number_format($value).'</span>');
    }

    /**
     * @return array<string, string>
     */
    private function statCardAttributes(): array
    {
        return [
            'style' => 'min-height: 9.5rem; padding-top: 1.1rem; padding-bottom: 1.1rem;',
        ];
    }
}
