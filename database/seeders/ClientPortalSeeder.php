<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientPortalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creator = User::query()->first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Client::factory()
            ->count(5)
            ->create()
            ->each(function (Client $client) use ($creator): void {
                Project::factory()
                    ->count(2)
                    ->for($client)
                    ->create(['created_by' => $creator->id])
                    ->each(function (Project $project) use ($creator): void {
                        ProjectUpdate::factory()
                            ->count(3)
                            ->for($project)
                            ->create(['created_by' => $creator->id]);

                        ProjectFile::factory()
                            ->count(2)
                            ->for($project)
                            ->create(['created_by' => $creator->id]);

                        SupportTicket::factory()
                            ->count(2)
                            ->for($project)
                            ->create(['created_by' => $creator->id])
                            ->each(function (SupportTicket $ticket) use ($creator): void {
                                SupportTicketComment::factory()
                                    ->count(2)
                                    ->for($ticket)
                                    ->create(['created_by' => $creator->id]);
                            });
                    });
            });
    }
}
