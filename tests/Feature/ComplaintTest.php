<?php

use App\Enums\ComplaintStatus;
use App\Jobs\SendComplaintNotification;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A member who can submit and view their own complaints.
 */
function complaintMember(): User
{
    $member = User::factory()->create();
    $member->assignRole('Member');

    return $member;
}

/**
 * A staff user who can triage complaints, with a WhatsApp number.
 */
function complaintHandler(): User
{
    $handler = User::factory()->create(['wa_number' => '0770000000']);
    $handler->givePermissionTo('admin', 'complaints.view-all', 'complaints.manage', 'settings.manage');

    return $handler;
}

test('a member submits a complaint with a photo and it reaches the configured recipients', function () {
    Storage::fake('local');
    Bus::fake([SendComplaintNotification::class]);

    $recipient = complaintHandler();
    app(Settings::class)->set(Settings::COMPLAINT_NOTIFY_USER_IDS, [$recipient->id]);

    $member = complaintMember();

    $this->actingAs($member)
        ->post(route('my.complaints.store'), [
            'category' => 'leak',
            'subject' => 'Pipe leaking at the gate',
            'description' => 'Water is leaking near the meter since this morning.',
            'attachments' => [UploadedFile::fake()->image('leak.jpg')],
        ])
        ->assertRedirect();

    $complaint = Complaint::query()->firstOrFail();

    expect($complaint->user_id)->toBe($member->id)
        ->and($complaint->status)->toBe(ComplaintStatus::Open)
        ->and($complaint->complaint_number)->toStartWith('CMP-')
        ->and($complaint->messages()->count())->toBe(1);

    $message = $complaint->messages()->first();
    expect($message->body)->toContain('leaking')
        ->and($message->attachments()->count())->toBe(1);

    Bus::assertDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $job): bool => $job->recipient->is($recipient),
    );
});

test('another member cannot view a complaint that is not theirs', function () {
    $owner = complaintMember();
    $complaint = Complaint::factory()->for($owner, 'member')->create();

    $this->actingAs(complaintMember())
        ->get(route('my.complaints.show', $complaint))
        ->assertForbidden();
});

test('assigning a complaint notifies the new handler and moves it in progress', function () {
    Bus::fake([SendComplaintNotification::class]);

    $complaint = Complaint::factory()->for(complaintMember(), 'member')->create();
    $handler = complaintHandler();

    $this->actingAs(complaintHandler())
        ->post(route('admin.complaints.assign', $complaint), ['handler_ids' => [$handler->id]])
        ->assertRedirect();

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::InProgress)
        ->and($complaint->handlers()->count())->toBe(1);

    Bus::assertDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $job): bool => $job->recipient->is($handler),
    );
});

test('after assignment a member reply notifies the handler, not the wider list', function () {
    Bus::fake([SendComplaintNotification::class]);

    $listRecipient = complaintHandler();
    app(Settings::class)->set(Settings::COMPLAINT_NOTIFY_USER_IDS, [$listRecipient->id]);

    $member = complaintMember();
    $handler = complaintHandler();
    $complaint = Complaint::factory()->for($member, 'member')->create(['status' => ComplaintStatus::InProgress]);
    $complaint->handlers()->attach($handler->id, ['assigned_at' => now()]);

    $this->actingAs($member)
        ->post(route('my.complaints.reply', $complaint), ['body' => 'Any update on this?'])
        ->assertRedirect();

    Bus::assertDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $job): bool => $job->recipient->is($handler),
    );
    Bus::assertNotDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $job): bool => $job->recipient->is($listRecipient),
    );
});

test('an admin closes a complaint with a note and the member is notified', function () {
    Bus::fake([SendComplaintNotification::class]);

    $member = complaintMember();
    $handler = complaintHandler();
    $complaint = Complaint::factory()->for($member, 'member')->create(['status' => ComplaintStatus::InProgress]);
    $complaint->handlers()->attach($handler->id, ['assigned_at' => now()]);

    $this->actingAs($handler)
        ->post(route('admin.complaints.close', $complaint), ['note' => 'Valve replaced and tested.'])
        ->assertRedirect();

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Closed)
        ->and($complaint->closure_note)->toBe('Valve replaced and tested.');

    Bus::assertDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $job): bool => $job->recipient->is($member),
    );
});

test('an admin cannot close a complaint without a note', function () {
    $complaint = Complaint::factory()->for(complaintMember(), 'member')->create();

    $this->actingAs(complaintHandler())
        ->post(route('admin.complaints.close', $complaint), ['note' => ''])
        ->assertSessionHasErrors('note');

    expect($complaint->refresh()->status)->not->toBe(ComplaintStatus::Closed);
});

test('a member can close their own complaint', function () {
    Bus::fake([SendComplaintNotification::class]);

    $member = complaintMember();
    $complaint = Complaint::factory()->for($member, 'member')->create();

    $this->actingAs($member)
        ->post(route('my.complaints.close', $complaint), ['note' => 'Sorted itself out.'])
        ->assertRedirect();

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Closed);
});

test('replying to a closed complaint is rejected', function () {
    $member = complaintMember();
    $complaint = Complaint::factory()->for($member, 'member')->closed()->create();

    $this->actingAs($member)
        ->post(route('my.complaints.reply', $complaint), ['body' => 'One more thing'])
        ->assertForbidden();
});

test('a member cannot reach the admin complaint queue', function () {
    $this->actingAs(complaintMember())
        ->get(route('admin.complaints.index'))
        ->assertForbidden();
});

test('a settings manager saves the complaint notification recipients', function () {
    $manager = complaintHandler();
    $recipient = complaintMember();

    $this->actingAs($manager)
        ->put(route('admin.settings.notifications.update'), [
            'complaint_notify_user_ids' => [$recipient->id],
        ])
        ->assertRedirect();

    expect(app(Settings::class)->get(Settings::COMPLAINT_NOTIFY_USER_IDS))->toBe([$recipient->id]);
});

test('the member complaint pages render', function () {
    $member = complaintMember();
    $complaint = Complaint::factory()->for($member, 'member')->create();

    $this->actingAs($member)->get(route('my.complaints.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('my/complaints/Index'));

    $this->actingAs($member)->get(route('my.complaints.create'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('my/complaints/Create'));

    $this->actingAs($member)->get(route('my.complaints.show', $complaint))
        ->assertOk()->assertInertia(fn ($page) => $page->component('my/complaints/Show'));
});

test('the admin complaint and settings pages render', function () {
    $manager = complaintHandler();
    $complaint = Complaint::factory()->for(complaintMember(), 'member')->create();

    $this->actingAs($manager)->get(route('admin.complaints.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/complaints/Index'));

    $this->actingAs($manager)->get(route('admin.complaints.show', $complaint))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/complaints/Show'));

    $this->actingAs($manager)->get(route('admin.settings.notifications.edit'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/settings/Notifications'));
});

test('an unrelated member cannot download complaint evidence', function () {
    Storage::fake('local');

    $owner = complaintMember();

    $this->actingAs($owner)->post(route('my.complaints.store'), [
        'category' => 'leak',
        'subject' => 'Leak',
        'description' => 'A leak with a photo.',
        'attachments' => [UploadedFile::fake()->image('leak.jpg')],
    ]);

    $attachment = Complaint::query()->firstOrFail()->messages()->first()->attachments()->firstOrFail();

    $this->actingAs($owner)->get(route('attachments.download', $attachment))->assertOk();
    $this->actingAs(complaintMember())->get(route('attachments.download', $attachment))->assertForbidden();
});
