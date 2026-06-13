<?php

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceJobStatus;
use App\Models\Complaint;
use App\Models\MaintenanceJob;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->periodStart = now()->startOfMonth()->addDay();
});

test('the summary tiles count complaints and jobs by status and average resolution time', function () {
    Complaint::factory()->create(['status' => ComplaintStatus::Open, 'submitted_at' => $this->periodStart]);
    Complaint::factory()->create(['status' => ComplaintStatus::InProgress, 'submitted_at' => $this->periodStart]);
    Complaint::factory()->closed()->create([
        'submitted_at' => $this->periodStart,
        'closed_at' => $this->periodStart->copy()->addDays(2),
    ]);
    Complaint::factory()->closed()->create([
        'submitted_at' => $this->periodStart,
        'closed_at' => $this->periodStart->copy()->addDays(3),
    ]);

    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::Assigned, 'scheduled_date' => $this->periodStart]);
    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::InProgress, 'scheduled_date' => $this->periodStart]);
    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::Cancelled, 'scheduled_date' => $this->periodStart]);
    MaintenanceJob::factory()->completed()->create([
        'scheduled_date' => $this->periodStart,
        'completed_at' => $this->periodStart->copy()->addDay(),
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/ComplaintsJobs')
            ->where('summary.complaint_status_counts.open', 1)
            ->where('summary.complaint_status_counts.in_progress', 1)
            ->where('summary.complaint_status_counts.closed', 2)
            ->where('summary.job_status_counts.assigned', 1)
            ->where('summary.job_status_counts.in_progress', 1)
            ->where('summary.job_status_counts.completed', 1)
            ->where('summary.job_status_counts.cancelled', 1)
            ->where('summary.avg_resolution_days', 2.5)
            ->has('jobs.data', 4)
            ->has('complaints', 4));
});

test('complaints and jobs outside the requested period are excluded', function () {
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();

    Complaint::factory()->create(['status' => ComplaintStatus::Open, 'submitted_at' => $lastMonth]);
    Complaint::factory()->create(['status' => ComplaintStatus::Open, 'submitted_at' => $this->periodStart]);

    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::Assigned, 'scheduled_date' => $lastMonth]);
    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::Assigned, 'scheduled_date' => $this->periodStart]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.complaint_status_counts.open', 1)
            ->where('summary.job_status_counts.assigned', 1)
            ->has('jobs.data', 1)
            ->has('complaints', 1));
});

test('the date_basis filter scopes jobs by scheduled or completed date', function () {
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDay();

    // Scheduled last month, but completed within the current period.
    MaintenanceJob::factory()->completed()->create([
        'scheduled_date' => $lastMonth,
        'completed_at' => $this->periodStart,
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page->has('jobs.data', 0));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', [
            'period_type' => 'month',
            'month' => now()->format('Y-m'),
            'date_basis' => 'completed',
        ]))
        ->assertInertia(fn ($page) => $page->has('jobs.data', 1));
});

test('the job status and complaint status filters narrow the results', function () {
    Complaint::factory()->create(['status' => ComplaintStatus::Open, 'submitted_at' => $this->periodStart]);
    Complaint::factory()->closed()->create([
        'submitted_at' => $this->periodStart,
        'closed_at' => $this->periodStart->copy()->addDay(),
    ]);

    MaintenanceJob::factory()->create(['status' => MaintenanceJobStatus::Assigned, 'scheduled_date' => $this->periodStart]);
    MaintenanceJob::factory()->completed()->create([
        'scheduled_date' => $this->periodStart,
        'completed_at' => $this->periodStart->copy()->addDay(),
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', ['job_status' => 'completed', 'complaint_status' => 'closed']))
        ->assertInertia(fn ($page) => $page
            ->has('jobs.data', 1)
            ->where('jobs.data.0.status', 'completed')
            ->has('complaints', 1)
            ->where('complaints.0.status', 'closed'));
});

test('the category filter narrows complaints and their linked jobs', function () {
    $leakComplaint = Complaint::factory()->create([
        'category' => ComplaintCategory::Leak,
        'submitted_at' => $this->periodStart,
    ]);
    $blockageComplaint = Complaint::factory()->create([
        'category' => ComplaintCategory::Blockage,
        'submitted_at' => $this->periodStart,
    ]);

    MaintenanceJob::factory()->create([
        'complaint_id' => $leakComplaint->id,
        'scheduled_date' => $this->periodStart,
    ]);
    MaintenanceJob::factory()->create([
        'complaint_id' => $blockageComplaint->id,
        'scheduled_date' => $this->periodStart,
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', ['category' => 'leak']))
        ->assertInertia(fn ($page) => $page
            ->has('jobs.data', 1)
            ->where('jobs.data.0.complaint_number', $leakComplaint->complaint_number)
            ->has('complaints', 1)
            ->where('complaints.0.complaint_number', $leakComplaint->complaint_number));
});

test('the assignee filter narrows the job completion log', function () {
    $assignee = User::factory()->create();

    $assignedJob = MaintenanceJob::factory()->create(['scheduled_date' => $this->periodStart]);
    $assignedJob->assignees()->attach($assignee->id, ['assigned_at' => now()]);

    MaintenanceJob::factory()->create(['scheduled_date' => $this->periodStart]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.index', ['assignee' => $assignee->id]))
        ->assertInertia(fn ($page) => $page
            ->has('jobs.data', 1)
            ->where('jobs.data.0.job_number', $assignedJob->job_number)
            ->where('jobs.data.0.assignees', $assignee->name));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.jobs.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    MaintenanceJob::factory()->create(['scheduled_date' => $this->periodStart]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.jobs.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Job number');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.jobs.pdf'))
        ->assertForbidden();
});
