<?php

use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceJobStatus;
use App\Jobs\SendComplaintNotification;
use App\Jobs\SendJobNotification;
use App\Models\Complaint;
use App\Models\MaintenanceJob;
use App\Models\User;
use App\Services\MaintenanceJobService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A staff user who can create, assign, and manage jobs, with a WhatsApp number.
 */
function jobAdmin(): User
{
    $admin = User::factory()->create(['wa_number' => '0770000001']);
    $admin->givePermissionTo(
        'admin',
        'complaints.view-all', 'complaints.manage',
        'maintenance-jobs.view-all', 'maintenance-jobs.create',
        'maintenance-jobs.assign', 'maintenance-jobs.update-status', 'maintenance-jobs.view-assigned',
    );

    return $admin;
}

/**
 * A member who can be assigned jobs.
 */
function jobAssignee(): User
{
    $user = User::factory()->create(['wa_number' => '0771111111']);
    $user->assignRole('Member');

    return $user;
}

test('an admin creates an ad-hoc job and only the assignee is notified', function () {
    Bus::fake([SendJobNotification::class]);

    $assignee = jobAssignee();

    $this->actingAs(jobAdmin())
        ->post(route('admin.maintenance-jobs.store'), [
            'title' => 'Clean the tank area',
            'description' => 'Monthly cleanup of the storage tank surroundings.',
            'scheduled_date' => now()->addWeek()->toDateString(),
            'assignee_ids' => [$assignee->id],
        ])
        ->assertRedirect();

    $job = MaintenanceJob::query()->firstOrFail();

    expect($job->job_number)->toStartWith('JOB-')
        ->and($job->complaint_id)->toBeNull()
        ->and($job->assignees()->count())->toBe(1)
        ->and($assignee->assignedJobs()->count())->toBe(1);

    Bus::assertDispatched(
        SendJobNotification::class,
        fn (SendJobNotification $j): bool => $j->recipient->is($assignee),
    );
});

test('creating a job from a complaint moves it in progress and notes the thread', function () {
    Bus::fake([SendJobNotification::class, SendComplaintNotification::class]);

    $admin = jobAdmin();
    $complaint = Complaint::factory()->for(jobAssignee(), 'member')->create();
    $complaint->handlers()->attach($admin->id, ['assigned_at' => now()]);

    $assignee = jobAssignee();

    $this->actingAs($admin)
        ->post(route('admin.maintenance-jobs.store'), [
            'complaint_id' => $complaint->id,
            'title' => 'Replace the broken valve',
            'description' => 'Valve at the connection is broken.',
            'scheduled_date' => now()->addDay()->toDateString(),
            'assignee_ids' => [$assignee->id],
        ])
        ->assertRedirect();

    $complaint->refresh();
    expect($complaint->status)->toBe(ComplaintStatus::InProgress)
        ->and($complaint->messages()->whereNull('user_id')->where('body', 'like', '%JOB-%')->exists())->toBeTrue();
});

test('a linked job shows on the complaint and its lifecycle syncs to the thread', function () {
    $handler = jobAdmin();
    $member = jobAssignee();
    $complaint = Complaint::factory()->for($member, 'member')->create(['status' => ComplaintStatus::InProgress]);
    $complaint->handlers()->attach($handler->id, ['assigned_at' => now()]);

    $assignee = jobAssignee();
    $job = MaintenanceJob::factory()->create(['complaint_id' => $complaint->id, 'created_by' => $handler->id]);
    $job->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    // the complaint page surfaces the linked job and offers to create more
    $this->actingAs($handler)->get(route('admin.complaints.show', $complaint))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/complaints/Show')
            ->has('jobs', 1)
            ->where('jobs.0.job_number', $job->job_number)
            ->where('can.create_job', true));

    // starting the job records a note back on the complaint thread
    $this->actingAs($assignee)->post(route('my-jobs.status', $job), ['status' => 'in_progress'])->assertRedirect();

    expect($complaint->messages()->whereNull('user_id')->where('body', 'like', '%started%')->exists())->toBeTrue();
});

test('an assignee starts and completes a job, notifying the handler to verify', function () {
    Bus::fake([SendJobNotification::class, SendComplaintNotification::class]);

    $handler = jobAdmin();
    $member = jobAssignee();
    $complaint = Complaint::factory()->for($member, 'member')->create(['status' => ComplaintStatus::InProgress]);
    $complaint->handlers()->attach($handler->id, ['assigned_at' => now()]);

    $assignee = jobAssignee();
    $job = MaintenanceJob::factory()->create(['complaint_id' => $complaint->id, 'created_by' => $handler->id]);
    $job->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    $this->actingAs($assignee)
        ->post(route('my-jobs.status', $job), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($job->refresh()->status)->toBe(MaintenanceJobStatus::InProgress);

    $this->actingAs($assignee)
        ->post(route('my-jobs.status', $job), ['status' => 'completed', 'notes' => 'Valve replaced and tested.'])
        ->assertRedirect();

    $job->refresh();
    expect($job->status)->toBe(MaintenanceJobStatus::Completed)
        ->and($job->completion_notes)->toBe('Valve replaced and tested.')
        // the completion is recorded on the complaint thread as a silent system message
        ->and($complaint->messages()->whereNull('user_id')->where('body', 'like', '%completed%')->exists())->toBeTrue();

    // the handler is asked to verify...
    Bus::assertDispatched(
        SendJobNotification::class,
        fn (SendJobNotification $j): bool => $j->recipient->is($handler),
    );
    // ...but the member is NOT notified at completion (they hear at closure)
    Bus::assertNotDispatched(
        SendComplaintNotification::class,
        fn (SendComplaintNotification $j): bool => $j->recipient->is($member),
    );
});

test('completing a job requires notes and forbids illegal transitions', function () {
    $admin = jobAdmin();
    $assignee = jobAssignee();
    $job = MaintenanceJob::factory()->create(['created_by' => $admin->id]);
    $job->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    // assigned -> completed is illegal (must start first)
    $this->actingAs($assignee)
        ->post(route('my-jobs.status', $job), ['status' => 'completed', 'notes' => 'done'])
        ->assertSessionHasErrors('status');

    app(MaintenanceJobService::class)->updateStatus($job, MaintenanceJobStatus::InProgress, null, $admin);

    // completing without notes is rejected
    $this->actingAs($assignee)
        ->post(route('my-jobs.status', $job->refresh()), ['status' => 'completed', 'notes' => ''])
        ->assertSessionHasErrors('notes');
});

test('a linked-job assignee may view the complaint', function () {
    $member = jobAssignee();
    $complaint = Complaint::factory()->for($member, 'member')->create(['status' => ComplaintStatus::InProgress]);

    $assignee = jobAssignee();
    $job = MaintenanceJob::factory()->create(['complaint_id' => $complaint->id, 'created_by' => jobAdmin()->id]);
    $job->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    $this->actingAs($assignee)
        ->get(route('my.complaints.show', $complaint))
        ->assertOk();

    // an unrelated member still cannot
    $this->actingAs(jobAssignee())
        ->get(route('my.complaints.show', $complaint))
        ->assertForbidden();
});

test('an assignee sees the job under my jobs but a stranger cannot open it', function () {
    $assignee = jobAssignee();
    $job = MaintenanceJob::factory()->create(['created_by' => jobAdmin()->id]);
    $job->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    $this->actingAs($assignee)->get(route('my-jobs.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('my-jobs/Index'));

    $this->actingAs($assignee)->get(route('my-jobs.show', $job))
        ->assertOk()->assertInertia(fn ($page) => $page->component('my-jobs/Show'));

    $this->actingAs(jobAssignee())->get(route('my-jobs.show', $job))->assertForbidden();
});

test('a member cannot reach the admin maintenance-jobs queue', function () {
    $this->actingAs(jobAssignee())
        ->get(route('admin.maintenance-jobs.index'))
        ->assertForbidden();
});

test('the admin maintenance-job pages render', function () {
    $admin = jobAdmin();
    $job = MaintenanceJob::factory()->create(['created_by' => $admin->id]);
    $job->assignees()->attach(jobAssignee()->id, ['assigned_at' => now()]);

    $this->actingAs($admin)->get(route('admin.maintenance-jobs.index'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/maintenance-jobs/Index'));

    $this->actingAs($admin)->get(route('admin.maintenance-jobs.create'))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/maintenance-jobs/Create'));

    $this->actingAs($admin)->get(route('admin.maintenance-jobs.show', $job))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/maintenance-jobs/Show'));

    $this->actingAs($admin)->get(route('admin.maintenance-jobs.edit', $job))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/maintenance-jobs/Edit'));
});
