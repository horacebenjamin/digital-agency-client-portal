<?php

namespace App\Filament\Resources\SupportTicketComments\Schemas;

use App\Models\SupportTicket;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupportTicketCommentForm
{
    /**
     * @return array<int, string>
     */
    public static function supportTicketOptions(?string $search = null): array
    {
        return SupportTicket::query()
            ->with(['project.client'])
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhereHas('project', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('project.client', fn ($query) => $query->where('company_name', 'like', "%{$search}%"));

                    if (is_numeric($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (SupportTicket $ticket): array => [
                $ticket->id => self::supportTicketLabel($ticket),
            ])
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('support_ticket_id')
                    ->label('Support ticket')
                    ->options(fn (): array => self::supportTicketOptions())
                    ->getSearchResultsUsing(fn (string $search): array => self::supportTicketOptions($search))
                    ->getOptionLabelUsing(function ($value): ?string {
                        $ticket = SupportTicket::query()
                            ->with(['project.client'])
                            ->find($value);

                        return $ticket ? self::supportTicketLabel($ticket) : null;
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Toggle::make('is_internal')
                    ->default(false),
            ]);
    }

    private static function supportTicketLabel(SupportTicket $ticket): string
    {
        $project = $ticket->project?->title ?? 'No project';
        $client = $ticket->project?->client?->company_name ?? 'No client';

        return "#{$ticket->id} - {$ticket->title} ({$project} / {$client})";
    }
}
