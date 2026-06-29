<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpdate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Models\User;
use App\Notifications\PaymentRequestSent;
use App\Notifications\ProjectFileUploaded;
use App\Notifications\ProjectUpdatePublished;
use App\Notifications\SupportTicketReplyCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_published_project_update_notifies_users_linked_to_project_client(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $clientUser = User::factory()->for($client)->create();
        $otherUser = User::factory()->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Kickoff complete',
            'status' => 'published',
        ]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame(0, $otherUser->fresh()->notifications()->count());
        $this->assertSame('Kickoff complete', $clientUser->notifications()->first()->data['body']);
    }

    public function test_draft_project_update_does_not_notify_clients(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        ProjectUpdate::factory()->for($project)->create([
            'status' => 'draft',
        ]);

        $this->assertSame(0, $clientUser->fresh()->notifications()->count());
    }

    public function test_draft_project_update_changed_to_published_notifies_once(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        $projectUpdate = ProjectUpdate::factory()->for($project)->create([
            'title' => 'Launch plan ready',
            'status' => 'draft',
        ]);

        $this->assertSame(0, $clientUser->fresh()->notifications()->count());

        $projectUpdate->update(['status' => 'published']);
        $projectUpdate->update(['title' => 'Launch plan ready for review']);

        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame('Launch plan ready', $clientUser->notifications()->first()->data['body']);
    }

    public function test_project_file_upload_notifies_users_linked_to_project_client(): void
    {
        Storage::fake('local');

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $clientUser = User::factory()->for($client)->create();
        $otherUser = User::factory()->create();

        Storage::disk('local')->put('project-files/design.pdf', 'design contents');

        ProjectFile::factory()->for($project)->create([
            'name' => 'Design.pdf',
            'path' => 'project-files/design.pdf',
            'disk' => 'local',
        ]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame(0, $otherUser->fresh()->notifications()->count());
        $this->assertSame('Design.pdf', $clientUser->notifications()->first()->data['body']);
    }

    public function test_support_ticket_admin_reply_notifies_users_linked_to_ticket_client(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $ticket = SupportTicket::factory()->for($project)->create(['title' => 'Homepage feedback']);
        $clientUser = User::factory()->for($client)->create();
        $otherClientUser = User::factory()->for($otherClient)->create();
        $adminUser = User::factory()->create(['client_id' => null]);

        SupportTicketComment::factory()->for($ticket)->for($adminUser, 'creator')->create([
            'body' => 'We have updated the homepage copy.',
            'is_internal' => false,
        ]);

        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame(0, $otherClientUser->fresh()->notifications()->count());
        $this->assertSame('Homepage feedback', $clientUser->notifications()->first()->data['body']);
    }

    public function test_internal_support_ticket_comment_does_not_notify_clients(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $ticket = SupportTicket::factory()->for($project)->create();
        $clientUser = User::factory()->for($client)->create();
        $adminUser = User::factory()->create(['client_id' => null]);

        $comment = SupportTicketComment::factory()->for($ticket)->for($adminUser, 'creator')->create([
            'is_internal' => true,
        ]);

        Notification::assertNothingSent();
        $this->assertSame([], (new SupportTicketReplyCreated($comment))->via($clientUser));
        $this->assertSame(0, $clientUser->fresh()->notifications()->count());
    }

    public function test_client_portal_notifications_use_database_and_mail_channels(): void
    {
        Notification::fake();
        Storage::fake('local');

        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $clientUser = User::factory()->for($client)->create();
        $adminUser = User::factory()->create(['client_id' => null]);
        $ticket = SupportTicket::factory()->for($project)->create(['title' => 'Homepage feedback']);

        $projectUpdate = ProjectUpdate::factory()->for($project)->create([
            'title' => 'Kickoff complete',
            'body' => 'The kickoff notes are ready to review.',
            'status' => 'published',
        ]);

        Storage::disk('local')->put('project-files/design.pdf', 'design contents');
        $projectFile = ProjectFile::factory()->for($project)->create([
            'name' => 'Design.pdf',
            'path' => 'project-files/design.pdf',
            'disk' => 'local',
        ]);

        $supportComment = SupportTicketComment::factory()->for($ticket)->for($adminUser, 'creator')->create([
            'body' => 'We have updated the homepage copy.',
            'is_internal' => false,
        ]);

        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Website deposit',
            'amount' => 125000,
            'status' => 'sent',
        ]);

        Notification::assertSentTo($clientUser, ProjectUpdatePublished::class, function (ProjectUpdatePublished $notification, array $channels) use ($clientUser, $project, $projectUpdate): bool {
            $mail = $notification->toMail($clientUser);

            return $channels === ['database', 'mail']
                && $mail->subject === 'New project update: Kickoff complete'
                && $mail->actionUrl === route('client.projects.show', $project)
                && $notification->projectUpdate->is($projectUpdate);
        });

        Notification::assertSentTo($clientUser, ProjectFileUploaded::class, function (ProjectFileUploaded $notification, array $channels) use ($clientUser, $project, $projectFile): bool {
            $mail = $notification->toMail($clientUser);

            return $channels === ['database', 'mail']
                && $mail->subject === 'New project file: Design.pdf'
                && $mail->actionUrl === route('client.projects.show', $project)
                && $notification->projectFile->is($projectFile);
        });

        Notification::assertSentTo($clientUser, SupportTicketReplyCreated::class, function (SupportTicketReplyCreated $notification, array $channels) use ($clientUser, $supportComment, $ticket): bool {
            $mail = $notification->toMail($clientUser);

            return $channels === ['database', 'mail']
                && $mail->subject === 'New support ticket reply: Homepage feedback'
                && $mail->actionUrl === route('client.support-tickets.show', $ticket)
                && $notification->comment->is($supportComment);
        });

        Notification::assertSentTo($clientUser, PaymentRequestSent::class, function (PaymentRequestSent $notification, array $channels) use ($clientUser, $paymentRequest): bool {
            $mail = $notification->toMail($clientUser);

            return $channels === ['database', 'mail']
                && $mail->subject === 'New payment request: Website deposit'
                && $mail->actionUrl === route('client.billing.index')
                && in_array('Amount: GBP 1,250.00', $mail->introLines, true)
                && $notification->paymentRequest->is($paymentRequest);
        });
    }

    public function test_client_support_ticket_reply_does_not_notify_other_client_users(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $ticket = SupportTicket::factory()->for($project)->create();
        $author = User::factory()->for($client)->create();
        $otherClientUser = User::factory()->for($client)->create();

        SupportTicketComment::factory()->for($ticket)->for($author, 'creator')->create([
            'is_internal' => false,
        ]);

        $this->assertSame(0, $author->fresh()->notifications()->count());
        $this->assertSame(0, $otherClientUser->fresh()->notifications()->count());
    }

    public function test_payment_request_sent_notifies_users_linked_to_client(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $project = Project::factory()->for($client)->create(['title' => 'Website Redesign']);
        $clientUser = User::factory()->for($client)->create();
        $otherClientUser = User::factory()->for($otherClient)->create();

        PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Website deposit',
            'status' => 'sent',
        ]);

        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame(0, $otherClientUser->fresh()->notifications()->count());
        $this->assertSame('Website deposit', $clientUser->notifications()->first()->data['body']);
    }

    public function test_draft_payment_request_does_not_notify_clients(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        PaymentRequest::factory()->for($client)->for($project)->create([
            'status' => 'draft',
        ]);

        $this->assertSame(0, $clientUser->fresh()->notifications()->count());
    }

    public function test_draft_payment_request_changed_to_sent_notifies_once(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'title' => 'Final balance',
            'status' => 'draft',
        ]);

        $this->assertSame(0, $clientUser->fresh()->notifications()->count());

        $paymentRequest->update(['status' => 'sent']);
        $paymentRequest->update(['title' => 'Final project balance']);

        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
        $this->assertSame('Final balance', $clientUser->notifications()->first()->data['body']);
    }

    public function test_duplicate_sent_payment_request_edits_do_not_create_duplicate_notifications(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        $paymentRequest = PaymentRequest::factory()->for($client)->for($project)->create([
            'status' => 'sent',
        ]);

        $paymentRequest->update(['title' => 'Updated title']);
        $paymentRequest->update(['description' => 'Updated description']);
        $paymentRequest->update(['status' => 'draft']);
        $paymentRequest->update(['status' => 'sent']);

        $this->assertSame(1, $clientUser->fresh()->notifications()->count());
    }

    public function test_client_only_sees_their_own_notifications(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $otherProject = Project::factory()->for($otherClient)->create();
        $clientUser = User::factory()->for($client)->create();
        $otherUser = User::factory()->for($otherClient)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Owned notification',
            'status' => 'published',
        ]);
        ProjectUpdate::factory()->for($otherProject)->create([
            'title' => 'Private other notification',
            'status' => 'published',
        ]);

        $this->actingAs($clientUser)
            ->get(route('client.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Notifications/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.body', 'Owned notification')
            )
            ->assertDontSee('Private other notification');

        $this->assertSame(1, $otherUser->fresh()->notifications()->count());
    }

    public function test_client_can_mark_their_notification_as_read(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $clientUser = User::factory()->for($client)->create();

        ProjectUpdate::factory()->for($project)->create([
            'title' => 'Ready to review',
            'status' => 'published',
        ]);

        $notification = $clientUser->notifications()->first();

        $this->assertNull($notification->read_at);

        $this->actingAs($clientUser)
            ->patch(route('client.notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_client_cannot_mark_another_users_notification_as_read(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherProject = Project::factory()->for($otherClient)->create();
        $clientUser = User::factory()->for($client)->create();
        $otherUser = User::factory()->for($otherClient)->create();

        ProjectUpdate::factory()->for($otherProject)->create([
            'status' => 'published',
        ]);

        $notification = $otherUser->notifications()->first();

        $this->actingAs($clientUser)
            ->patch(route('client.notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('client.notifications.index'))
            ->assertRedirect(route('login'));

        $this->patch(route('client.notifications.read', '00000000-0000-0000-0000-000000000000'))
            ->assertRedirect(route('login'));
    }
}
