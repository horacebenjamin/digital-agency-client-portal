<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Config::set('mail.default', 'array');

        $agencyLead = User::query()->updateOrCreate(
            ['email' => 'emma@agency.test'],
            [
                'name' => 'Emma Roberts',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::parse('2026-05-01 09:00:00'),
            ],
        );
        $agencyLead->assignRole('project_manager');

        $developer = User::query()->updateOrCreate(
            ['email' => 'james@agency.test'],
            [
                'name' => 'James Patel',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::parse('2026-05-01 09:05:00'),
            ],
        );
        $developer->assignRole('developer');

        $client = Client::query()->updateOrCreate(
            ['email' => 'hello@northstarfitness.test'],
            [
                'company_name' => 'Northstar Fitness Ltd',
                'contact_name' => 'Sarah Mitchell',
                'phone' => '+44 20 7946 0821',
                'website' => 'https://northstarfitness.test',
                'status' => 'active',
                'notes' => 'Growing boutique fitness brand preparing to launch memberships, class packs, and instructor-led booking flows.',
            ],
        );

        $sarah = User::query()->updateOrCreate(
            ['email' => 'sarah@northstarfitness.test'],
            [
                'name' => 'Sarah Mitchell',
                'client_id' => $client->id,
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::parse('2026-05-02 10:00:00'),
            ],
        );
        $sarah->assignRole('client');

        $secondClient = Client::query()->updateOrCreate(
            ['email' => 'accounts@harbourandfield.test'],
            [
                'company_name' => 'Harbour & Field Studio',
                'contact_name' => 'Maya Chen',
                'phone' => '+44 161 555 0134',
                'website' => 'https://harbourandfield.test',
                'status' => 'active',
                'notes' => 'Secondary demo client used to validate client portal authorization boundaries.',
            ],
        );

        $maya = User::query()->updateOrCreate(
            ['email' => 'maya@harbourandfield.test'],
            [
                'name' => 'Maya Chen',
                'client_id' => $secondClient->id,
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::parse('2026-05-03 10:00:00'),
            ],
        );
        $maya->assignRole('client');

        $project = Project::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'title' => 'Northstar Fitness Membership Platform',
            ],
            [
                'created_by' => $agencyLead->id,
                'description' => 'A membership and class booking platform for a growing fitness business.',
                'status' => 'in_progress',
                'priority' => 'high',
                'progress_percentage' => 68,
                'started_at' => Carbon::parse('2026-05-18 09:30:00'),
                'due_date' => Carbon::parse('2026-08-21'),
                'created_at' => Carbon::parse('2026-05-16 14:20:00'),
                'updated_at' => Carbon::parse('2026-07-08 16:15:00'),
            ],
        );

        $this->seedProjectUpdates($project, $agencyLead, $developer);
        $this->seedProjectFiles($project, $agencyLead, $developer);
        $this->seedSupportTickets($project, $sarah, $agencyLead, $developer);
        $this->seedPaymentRequests($client, $project);
        $this->seedSecondClientProject($secondClient, $agencyLead);
        $this->shapeNotificationState($sarah);
    }

    private function seedProjectUpdates(Project $project, User $agencyLead, User $developer): void
    {
        $updates = [
            ['Membership dashboard approved', 'Sarah approved the revised member dashboard layout after the final content hierarchy review. The dashboard now highlights upcoming bookings, membership status, renewal prompts, and recommended classes without overwhelming returning members.', 'published', $agencyLead, '2026-06-03 11:15:00'],
            ['Stripe subscription flow completed', 'Monthly memberships, annual plans, free trial handling, and failed payment states have been connected to Stripe Checkout. The next pass is focused on webhook QA and customer portal copy.', 'published', $developer, '2026-06-14 15:40:00'],
            ['Class booking calendar implemented', 'The class timetable now supports instructor filters, waitlist messaging, booking cut-off windows, and member-only class visibility. We also added empty states for studios with no sessions scheduled.', 'published', $agencyLead, '2026-06-24 10:10:00'],
            ['Mobile navigation improved', 'We simplified the mobile menu around the highest-volume member journeys: book a class, manage membership, view account, and contact support. Tap targets have been increased and the sticky booking action is now live in staging.', 'published', $developer, '2026-07-02 13:30:00'],
            ['Admin reporting dashboard in progress', 'Revenue, attendance, and retention widgets are now being wired into the admin reporting view. We are validating the numbers against exported Stripe and booking data before sharing the next preview.', 'published', $agencyLead, '2026-07-08 16:15:00'],
            ['Internal launch checklist draft', 'Draft notes for agency QA before client review.', 'draft', $agencyLead, '2026-07-09 09:00:00'],
        ];

        foreach ($updates as [$title, $body, $status, $creator, $createdAt]) {
            ProjectUpdate::query()->updateOrCreate(
                ['project_id' => $project->id, 'title' => $title],
                [
                    'created_by' => $creator->id,
                    'body' => $body,
                    'status' => $status,
                    'created_at' => Carbon::parse($createdAt),
                    'updated_at' => Carbon::parse($createdAt)->addMinutes(12),
                ],
            );
        }
    }

    private function seedProjectFiles(Project $project, User $agencyLead, User $developer): void
    {
        $files = [
            ['membership-dashboard-v3.pdf', 'PDF walkthrough of the approved member dashboard and renewal prompts.', 'application/pdf', 824_000, $agencyLead, '2026-06-03 12:00:00'],
            ['brand-guidelines.pdf', 'Updated digital usage guide covering typography, colour, buttons, photography, and accessibility notes.', 'application/pdf', 1_942_000, $agencyLead, '2026-06-07 09:45:00'],
            ['mobile-navigation-preview.png', 'Annotated preview of the revised mobile navigation and sticky booking action.', 'image/png', 486_000, $developer, '2026-07-02 14:05:00'],
            ['stripe-checkout-flow.pdf', 'End-to-end Stripe subscription checkout flow including success, cancellation, and failed payment states.', 'application/pdf', 713_000, $developer, '2026-06-14 16:20:00'],
            ['user-acceptance-testing-checklist.docx', 'Client UAT checklist for membership purchase, class booking, account updates, and support handover.', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 152_000, $agencyLead, '2026-07-07 10:30:00'],
        ];

        foreach ($files as [$name, $description, $mimeType, $size, $creator, $createdAt]) {
            $path = 'demo/northstar/'.$name;
            Storage::disk('public')->put($path, $this->demoFileContents($name, $description));

            ProjectFile::query()->updateOrCreate(
                ['project_id' => $project->id, 'name' => $name],
                [
                    'created_by' => $creator->id,
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'description' => $description,
                    'status' => ProjectFile::STATUS_AVAILABLE,
                    'created_at' => Carbon::parse($createdAt),
                    'updated_at' => Carbon::parse($createdAt)->addMinutes(8),
                ],
            );
        }
    }

    private function seedSupportTickets(Project $project, User $sarah, User $agencyLead, User $developer): void
    {
        $openTicket = SupportTicket::query()->updateOrCreate(
            ['project_id' => $project->id, 'title' => 'Can we adjust the membership renewal reminder copy?'],
            [
                'created_by' => $sarah->id,
                'description' => 'The renewal reminder currently feels a little too transactional. Could we make the tone warmer and mention the member benefits they keep by renewing?',
                'status' => SupportTicket::STATUS_OPEN,
                'priority' => 'high',
                'due_date' => Carbon::parse('2026-07-14'),
                'created_at' => Carbon::parse('2026-07-09 09:20:00'),
                'updated_at' => Carbon::parse('2026-07-09 15:45:00'),
            ],
        );

        $this->comment($openTicket, $sarah, 'Could we also include a version for annual members? Their renewal email should feel more like a loyalty message than a billing reminder.', false, '2026-07-09 09:24:00');
        $this->comment($openTicket, $agencyLead, 'Yes, we can soften that. I will draft two versions today: one for monthly members and one for annual members, then add them to the copy deck for review.', false, '2026-07-09 11:05:00');
        $this->comment($openTicket, $developer, 'Internal note: copy is controlled in the notification template config. No engineering change needed unless Sarah asks for different send timing.', true, '2026-07-09 11:18:00');
        $this->comment($openTicket, $sarah, 'Thanks. Please keep the monthly version concise because it appears in both email and the member dashboard banner.', false, '2026-07-09 15:45:00');

        $resolvedTicket = SupportTicket::query()->updateOrCreate(
            ['project_id' => $project->id, 'title' => 'Logo spacing on mobile checkout header'],
            [
                'created_by' => $sarah->id,
                'description' => 'The Northstar logo looks too close to the basket summary on smaller phones during checkout.',
                'status' => SupportTicket::STATUS_RESOLVED,
                'priority' => 'medium',
                'due_date' => Carbon::parse('2026-06-28'),
                'completed_at' => Carbon::parse('2026-06-27 16:10:00'),
                'created_at' => Carbon::parse('2026-06-26 10:35:00'),
                'updated_at' => Carbon::parse('2026-06-27 16:10:00'),
            ],
        );

        $this->comment($resolvedTicket, $sarah, 'I noticed this while reviewing on an iPhone SE. It is only tight on the checkout screens, not the main site header.', false, '2026-06-26 10:38:00');
        $this->comment($resolvedTicket, $developer, 'Good spot. We have increased the header spacing at the smallest breakpoint and moved the basket summary under the logo when space is constrained.', false, '2026-06-27 14:20:00');
        $this->comment($resolvedTicket, $sarah, 'Confirmed, this looks much cleaner now. Happy for this to be marked resolved.', false, '2026-06-27 16:10:00');
    }

    private function seedPaymentRequests(Client $client, Project $project): void
    {
        PaymentRequest::query()->updateOrCreate(
            ['client_id' => $client->id, 'title' => 'Project deposit - membership platform'],
            [
                'project_id' => $project->id,
                'description' => 'Initial deposit covering discovery, UX architecture, and the first design sprint for the Northstar Fitness membership platform.',
                'amount' => 450000,
                'currency' => 'gbp',
                'status' => 'paid',
                'due_date' => Carbon::parse('2026-05-20'),
                'paid_at' => Carbon::parse('2026-05-19 13:42:00'),
                'stripe_checkout_session_id' => 'cs_test_northstar_deposit_paid',
                'stripe_payment_intent_id' => 'pi_test_northstar_deposit_paid',
                'created_at' => Carbon::parse('2026-05-16 15:00:00'),
                'updated_at' => Carbon::parse('2026-05-19 13:42:00'),
            ],
        );

        PaymentRequest::query()->updateOrCreate(
            ['client_id' => $client->id, 'title' => 'Milestone 2 - booking and subscription build'],
            [
                'project_id' => $project->id,
                'description' => 'Second milestone payment for the Stripe subscription flow, class booking calendar, mobile navigation improvements, and admin reporting build.',
                'amount' => 680000,
                'currency' => 'gbp',
                'status' => 'sent',
                'due_date' => Carbon::parse('2026-07-18'),
                'paid_at' => null,
                'stripe_checkout_session_id' => null,
                'stripe_payment_intent_id' => null,
                'created_at' => Carbon::parse('2026-07-08 09:30:00'),
                'updated_at' => Carbon::parse('2026-07-08 09:30:00'),
            ],
        );
    }

    private function seedSecondClientProject(Client $client, User $agencyLead): void
    {
        Project::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'title' => 'Harbour & Field Wholesale Portal',
            ],
            [
                'created_by' => $agencyLead->id,
                'description' => 'A private ordering portal for boutique retail stockists.',
                'status' => 'planning',
                'priority' => 'medium',
                'progress_percentage' => 18,
                'started_at' => Carbon::parse('2026-06-10 09:00:00'),
                'due_date' => Carbon::parse('2026-09-04'),
                'created_at' => Carbon::parse('2026-06-05 12:00:00'),
                'updated_at' => Carbon::parse('2026-06-10 09:00:00'),
            ],
        );
    }

    private function comment(SupportTicket $ticket, User $creator, string $body, bool $isInternal, string $createdAt): void
    {
        SupportTicketComment::query()->updateOrCreate(
            [
                'support_ticket_id' => $ticket->id,
                'created_by' => $creator->id,
                'body' => $body,
            ],
            [
                'is_internal' => $isInternal,
                'created_at' => Carbon::parse($createdAt),
                'updated_at' => Carbon::parse($createdAt),
            ],
        );
    }

    private function shapeNotificationState(User $user): void
    {
        $notifications = $user->notifications()->latest()->get();

        $notifications->each(function (DatabaseNotification $notification): void {
            $createdAt = match ($notification->type) {
                ProjectUpdatePublished::class => match ($notification->data['body'] ?? null) {
                    'Admin reporting dashboard in progress' => Carbon::parse('2026-07-08 16:16:00'),
                    'Mobile navigation improved' => Carbon::parse('2026-07-02 13:31:00'),
                    'Class booking calendar implemented' => Carbon::parse('2026-06-24 10:11:00'),
                    default => Carbon::parse('2026-06-14 15:41:00'),
                },
                ProjectFileUploaded::class => match ($notification->data['body'] ?? null) {
                    'user-acceptance-testing-checklist.docx' => Carbon::parse('2026-07-07 10:31:00'),
                    'mobile-navigation-preview.png' => Carbon::parse('2026-07-02 14:06:00'),
                    default => Carbon::parse('2026-06-14 16:21:00'),
                },
                SupportTicketReplyCreated::class => Carbon::parse('2026-07-09 11:06:00'),
                PaymentRequestSent::class => Carbon::parse('2026-07-08 09:31:00'),
                default => $notification->created_at,
            };

            $notification->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'read_at' => $this->isUnreadDemoNotification($notification) ? null : $createdAt->copy()->addHours(3),
            ])->save();
        });
    }

    private function isUnreadDemoNotification(DatabaseNotification $notification): bool
    {
        return match ($notification->type) {
            ProjectUpdatePublished::class => ($notification->data['body'] ?? null) === 'Admin reporting dashboard in progress',
            ProjectFileUploaded::class => ($notification->data['body'] ?? null) === 'user-acceptance-testing-checklist.docx',
            PaymentRequestSent::class => true,
            default => false,
        };
    }

    private function demoFileContents(string $name, string $description): string
    {
        return "Northstar Fitness demo file: {$name}\n\n{$description}\n\nThis seeded file is used by the demo client portal for download and portfolio screenshots.\n";
    }
}
