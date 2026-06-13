<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceJobStatus;
use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\Complaint;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R8 Complaint and Maintenance Job (docs/management-reports.md): the
 * operational service record — the job completion log alongside the
 * complaints raised in the period, filterable by status, category, assignee,
 * and the date basis a maintenance job is scoped by (scheduled or completed).
 */
class JobCompletionReportController extends ReportController
{
    protected function title(): string
    {
        return 'Complaint and Maintenance Job';
    }

    /**
     * Show the job completion log and the period's complaints.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $dateBasis = $this->requestedDateBasis($request);
        $jobStatus = MaintenanceJobStatus::tryFrom($request->string('job_status')->value());
        $complaintStatus = ComplaintStatus::tryFrom($request->string('complaint_status')->value());
        $category = ComplaintCategory::tryFrom($request->string('category')->value());
        $assigneeId = $this->requestedAssigneeId($request);

        $datatable = new Datatable(
            $request,
            searchColumns: ['job_number', 'title'],
            orderColumns: ['job_number', 'scheduled_date'],
            defaultSort: 'scheduled_date',
            defaultDirection: 'desc',
        );

        $jobs = $datatable->paginate($this->jobQuery($period, $dateBasis, $jobStatus, $category, $assigneeId))
            ->through(fn (MaintenanceJob $job): array => $this->jobLine($job));

        $complaints = $this->complaintQuery($period, $complaintStatus, $category)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (Complaint $complaint): array => $this->complaintLine($complaint))
            ->all();

        return Inertia::render('admin/reports/ComplaintsJobs', [
            'period' => $period->filters(),
            'reportFilters' => [
                'date_basis' => $dateBasis,
                'job_status' => $jobStatus?->value,
                'complaint_status' => $complaintStatus?->value,
                'category' => $category?->value,
                'assignee' => $assigneeId,
            ],
            'jobStatuses' => array_column(MaintenanceJobStatus::cases(), 'value'),
            'complaintStatuses' => array_column(ComplaintStatus::cases(), 'value'),
            'categories' => array_column(ComplaintCategory::cases(), 'value'),
            'assignees' => $this->assigneeOptions(),
            'summary' => $this->summary($period, $dateBasis, $jobStatus, $complaintStatus, $category, $assigneeId),
            'jobs' => $jobs,
            'complaints' => $complaints,
            'filters' => $datatable->filters(),
            'exportParams' => $this->exportParams($period, $dateBasis, $jobStatus, $complaintStatus, $category, $assigneeId),
        ]);
    }

    /**
     * Download the job completion log and complaints list as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $dateBasis = $this->requestedDateBasis($request);
        $jobStatus = MaintenanceJobStatus::tryFrom($request->string('job_status')->value());
        $complaintStatus = ComplaintStatus::tryFrom($request->string('complaint_status')->value());
        $category = ComplaintCategory::tryFrom($request->string('category')->value());
        $assigneeId = $this->requestedAssigneeId($request);

        return $this->pdfResponse('pdf.reports.jobs', $period, [
            'summary' => $this->summary($period, $dateBasis, $jobStatus, $complaintStatus, $category, $assigneeId),
            'jobs' => $this->jobQuery($period, $dateBasis, $jobStatus, $category, $assigneeId)
                ->orderBy('scheduled_date')
                ->orderBy('job_number')
                ->get()
                ->map(fn (MaintenanceJob $job): array => $this->jobLine($job))
                ->all(),
            'complaints' => $this->complaintQuery($period, $complaintStatus, $category)
                ->orderBy('submitted_at')
                ->get()
                ->map(fn (Complaint $complaint): array => $this->complaintLine($complaint))
                ->all(),
            'dateBasis' => $dateBasis,
        ]);
    }

    /**
     * Export the job completion log as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $dateBasis = $this->requestedDateBasis($request);
        $jobStatus = MaintenanceJobStatus::tryFrom($request->string('job_status')->value());
        $category = ComplaintCategory::tryFrom($request->string('category')->value());
        $assigneeId = $this->requestedAssigneeId($request);

        $rows = $this->jobQuery($period, $dateBasis, $jobStatus, $category, $assigneeId)
            ->orderBy('scheduled_date')
            ->orderBy('job_number')
            ->get()
            ->map(fn (MaintenanceJob $job): array => [
                $job->job_number,
                $job->title,
                $job->complaint->complaint_number ?? '',
                $job->assignees->map(fn (User $user): string => $user->name)->implode(', '),
                $job->scheduled_date->toDateString(),
                $job->completed_at?->toDateString() ?? '',
                $job->status->value,
            ]);

        return $this->csvResponse($period, [
            'Job number', 'Title', 'Complaint number', 'Assignees', 'Scheduled date', 'Completed date', 'Status',
        ], $rows);
    }

    /**
     * The maintenance jobs within the period, scoped by the requested date
     * basis (scheduled or completed) and narrowed by the optional filters.
     *
     * @return Builder<MaintenanceJob>
     */
    protected function jobQuery(ReportPeriod $period, string $dateBasis, ?MaintenanceJobStatus $status, ?ComplaintCategory $category, ?int $assigneeId): Builder
    {
        return MaintenanceJob::query()
            ->with(['complaint:id,complaint_number,category', 'assignees:id,first_name,last_name'])
            ->when(
                $dateBasis === 'completed',
                fn (Builder $query) => $query->whereBetween('completed_at', [$period->start, $period->end]),
                fn (Builder $query) => $query->whereBetween('scheduled_date', [$period->start->toDateString(), $period->end->toDateString()]),
            )
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($category !== null, fn (Builder $query) => $query
                ->whereHas('complaint', fn (Builder $sub) => $sub->where('category', $category)))
            ->when($assigneeId !== null, fn (Builder $query) => $query
                ->whereHas('assignees', fn (Builder $sub) => $sub->where('users.id', $assigneeId)));
    }

    /**
     * The complaints submitted within the period, narrowed by the optional
     * filters.
     *
     * @return Builder<Complaint>
     */
    protected function complaintQuery(ReportPeriod $period, ?ComplaintStatus $status, ?ComplaintCategory $category): Builder
    {
        return Complaint::query()
            ->with('member:id,first_name,last_name')
            ->whereBetween('submitted_at', [$period->start, $period->end])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category));
    }

    /**
     * The job completion log row shape shared by the page and the PDF.
     *
     * @return array{id: int, job_number: string, title: string, complaint_number: string|null, assignees: string, scheduled_date: string, completed_at: string|null, status: string}
     */
    protected function jobLine(MaintenanceJob $job): array
    {
        return [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'title' => $job->title,
            'complaint_number' => $job->complaint?->complaint_number,
            'assignees' => $job->assignees->map(fn (User $user): string => $user->name)->implode(', '),
            'scheduled_date' => $job->scheduled_date->format('d M Y'),
            'completed_at' => $job->completed_at?->format('d M Y'),
            'status' => $job->status->value,
        ];
    }

    /**
     * The complaint row shape shared by the page and the PDF.
     *
     * @return array{id: int, complaint_number: string, subject: string, category: string, member: string, submitted_at: string, status: string, closed_at: string|null}
     */
    protected function complaintLine(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'complaint_number' => $complaint->complaint_number,
            'subject' => $complaint->subject,
            'category' => $complaint->category->value,
            'member' => $complaint->member->name,
            'submitted_at' => $complaint->submitted_at->format('d M Y'),
            'status' => $complaint->status->value,
            'closed_at' => $complaint->closed_at?->format('d M Y'),
        ];
    }

    /**
     * The headline tiles: complaint and job counts by status, and the
     * average resolution time (in days, to 1dp) for complaints closed in
     * the period. Computed in PHP to stay driver-agnostic.
     *
     * @return array{complaint_status_counts: array<string, int>, job_status_counts: array<string, int>, avg_resolution_days: float}
     */
    protected function summary(ReportPeriod $period, string $dateBasis, ?MaintenanceJobStatus $jobStatus, ?ComplaintStatus $complaintStatus, ?ComplaintCategory $category, ?int $assigneeId): array
    {
        $complaintStatusCounts = $this->complaintQuery($period, $complaintStatus, $category)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $jobStatusCounts = $this->jobQuery($period, $dateBasis, $jobStatus, $category, $assigneeId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        /** @var Collection<int, Complaint> $closedComplaints */
        $closedComplaints = $this->complaintQuery($period, $complaintStatus, $category)
            ->whereNotNull('closed_at')
            ->get(['submitted_at', 'closed_at']);

        $avgResolutionDays = $closedComplaints->isEmpty()
            ? 0.0
            : round($closedComplaints->avg(
                fn (Complaint $complaint): float => $complaint->submitted_at->diffInSeconds($complaint->closed_at) / 86400,
            ), 1);

        return [
            'complaint_status_counts' => collect(ComplaintStatus::cases())
                ->mapWithKeys(fn (ComplaintStatus $case): array => [$case->value => (int) $complaintStatusCounts->get($case->value, 0)])
                ->all(),
            'job_status_counts' => collect(MaintenanceJobStatus::cases())
                ->mapWithKeys(fn (MaintenanceJobStatus $case): array => [$case->value => (int) $jobStatusCounts->get($case->value, 0)])
                ->all(),
            'avg_resolution_days' => $avgResolutionDays,
        ];
    }

    /**
     * The users assigned to at least one maintenance job, for the assignee
     * filter's select options.
     *
     * @return list<array{id: int, name: string}>
     */
    protected function assigneeOptions(): array
    {
        $users = User::query()
            ->whereHas('assignedJobs')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all();

        return array_values($users);
    }

    protected function requestedDateBasis(Request $request): string
    {
        return $request->string('date_basis')->value() === 'completed' ? 'completed' : 'scheduled';
    }

    protected function requestedAssigneeId(Request $request): ?int
    {
        $id = $request->integer('assignee');

        return $id > 0 ? $id : null;
    }

    /**
     * The query string the export links reproduce this view with.
     *
     * @return array<string, string|int>
     */
    protected function exportParams(ReportPeriod $period, string $dateBasis, ?MaintenanceJobStatus $jobStatus, ?ComplaintStatus $complaintStatus, ?ComplaintCategory $category, ?int $assigneeId): array
    {
        return array_filter([
            ...$period->queryParameters(),
            'date_basis' => $dateBasis === 'scheduled' ? null : $dateBasis,
            'job_status' => $jobStatus?->value,
            'complaint_status' => $complaintStatus?->value,
            'category' => $category?->value,
            'assignee' => $assigneeId,
        ], fn (string|int|null $value): bool => $value !== null && $value !== '');
    }
}
