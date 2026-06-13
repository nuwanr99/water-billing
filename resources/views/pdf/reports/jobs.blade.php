@extends('pdf.reports.layout')

@section('content')
    <p class="muted" style="margin-bottom: 10px;">
        Jobs scoped by {{ $dateBasis === 'completed' ? 'completed date' : 'scheduled date' }}.
    </p>

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Complaints open</div>
                <div class="value">{{ $summary['complaint_status_counts']['open'] }}</div>
            </td>
            <td>
                <div class="label">Complaints in progress</div>
                <div class="value">{{ $summary['complaint_status_counts']['in_progress'] }}</div>
            </td>
            <td>
                <div class="label">Complaints closed</div>
                <div class="value">{{ $summary['complaint_status_counts']['closed'] }}</div>
            </td>
            <td>
                <div class="label">Avg. resolution</div>
                <div class="value">{{ $summary['avg_resolution_days'] }} days</div>
            </td>
        </tr>
    </table>

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Jobs assigned</div>
                <div class="value">{{ $summary['job_status_counts']['assigned'] }}</div>
            </td>
            <td>
                <div class="label">Jobs in progress</div>
                <div class="value">{{ $summary['job_status_counts']['in_progress'] }}</div>
            </td>
            <td>
                <div class="label">Jobs completed</div>
                <div class="value">{{ $summary['job_status_counts']['completed'] }}</div>
            </td>
            <td>
                <div class="label">Jobs cancelled</div>
                <div class="value">{{ $summary['job_status_counts']['cancelled'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Job completion log</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Job</th>
                <th>Title</th>
                <th>Complaint</th>
                <th>Assignees</th>
                <th>Scheduled</th>
                <th>Completed</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td>{{ $job['job_number'] }}</td>
                    <td>{{ $job['title'] }}</td>
                    <td>{{ $job['complaint_number'] ?? '—' }}</td>
                    <td>{{ $job['assignees'] ?: '—' }}</td>
                    <td>{{ $job['scheduled_date'] }}</td>
                    <td>{{ $job['completed_at'] ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $job['status'])) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No jobs in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Complaints in period</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Complaint</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Member</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Closed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($complaints as $complaint)
                <tr>
                    <td>{{ $complaint['complaint_number'] }}</td>
                    <td>{{ $complaint['subject'] }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $complaint['category'])) }}</td>
                    <td>{{ $complaint['member'] }}</td>
                    <td>{{ $complaint['submitted_at'] }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $complaint['status'])) }}</td>
                    <td>{{ $complaint['closed_at'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No complaints in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
