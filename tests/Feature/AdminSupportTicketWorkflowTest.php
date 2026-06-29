<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTicketComments\Schemas\SupportTicketCommentForm;
use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\RelationManagers\CommentsRelationManager;
use App\Models\Client;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Notifications\SupportTicketReplyCreated;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSupportTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();
    }

    public function test_staff_can_create_public_reply_from_admin_ticket_page(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $ticket = SupportTicket::factory()->for($project)->create();
        $clientUser = User::factory()->for($client)->create();
        $admin = User::factory()->create(['client_id' => null]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        Livewire::test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => EditSupportTicket::class,
        ])
            ->callTableAction('create', null, [
                'body' => 'We have shipped the fix.',
                'is_internal' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'created_by' => $admin->id,
            'body' => 'We have shipped the fix.',
            'is_internal' => false,
        ]);

        Notification::assertSentTo($clientUser, SupportTicketReplyCreated::class);
    }

    public function test_public_admin_replies_notify_and_email_the_client(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $ticket = SupportTicket::factory()->for($project)->create(['title' => 'Homepage issue']);
        $clientUser = User::factory()->for($client)->create();
        $admin = User::factory()->create(['client_id' => null]);

        $comment = SupportTicketComment::factory()->for($ticket)->for($admin, 'creator')->create([
            'body' => 'This is now ready to review.',
            'is_internal' => false,
        ]);

        Notification::assertSentTo($clientUser, SupportTicketReplyCreated::class, function (SupportTicketReplyCreated $notification, array $channels) use ($clientUser, $comment, $ticket): bool {
            $mail = $notification->toMail($clientUser);

            return $channels === ['database', 'mail']
                && $mail->subject === 'New support ticket reply: Homepage issue'
                && $mail->actionUrl === route('client.support-tickets.show', $ticket)
                && $notification->comment->is($comment);
        });
    }

    public function test_internal_admin_replies_do_not_notify_or_email_the_client(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $ticket = SupportTicket::factory()->for($project)->create();
        $clientUser = User::factory()->for($client)->create();
        $admin = User::factory()->create(['client_id' => null]);

        $comment = SupportTicketComment::factory()->for($ticket)->for($admin, 'creator')->create([
            'body' => 'Internal handoff note.',
            'is_internal' => true,
        ]);

        Notification::assertNothingSent();
        $this->assertSame([], (new SupportTicketReplyCreated($comment))->via($clientUser));
    }

    public function test_client_created_tickets_appear_in_comment_ticket_options(): void
    {
        $client = Client::factory()->create(['company_name' => 'Acme Co']);
        $clientUser = User::factory()->for($client)->create();
        $project = Project::factory()->for($client)->create(['title' => 'Client Website']);
        $ticket = SupportTicket::factory()->for($project)->for($clientUser, 'creator')->create([
            'title' => 'Client-created homepage bug',
        ]);

        $options = SupportTicketCommentForm::supportTicketOptions('homepage bug');

        $this->assertArrayHasKey($ticket->id, $options);
        $this->assertStringContainsString('Client-created homepage bug', $options[$ticket->id]);
        $this->assertStringContainsString('Client Website', $options[$ticket->id]);
        $this->assertStringContainsString('Acme Co', $options[$ticket->id]);
    }
}
