export type MaintenanceJobStatus =
  'assigned' | 'in_progress' | 'completed' | 'cancelled';

export const jobStatusLabels: Record<MaintenanceJobStatus, string> = {
  assigned: 'Assigned',
  in_progress: 'In progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

export const jobStatusClasses: Record<MaintenanceJobStatus, string> = {
  assigned:
    'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  in_progress:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  completed:
    'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
  cancelled: 'border-transparent bg-muted text-muted-foreground',
};
