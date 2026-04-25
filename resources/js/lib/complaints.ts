export type ComplaintStatus = 'open' | 'in_progress' | 'closed';
export type ComplaintCategory = 'leak' | 'blockage' | 'low_pressure' | 'other';

export const complaintStatusLabels: Record<ComplaintStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  closed: 'Closed',
};

export const complaintCategoryLabels: Record<ComplaintCategory, string> = {
  leak: 'Leak',
  blockage: 'Blockage',
  low_pressure: 'Low pressure',
  other: 'Other',
};

export const complaintStatusClasses: Record<ComplaintStatus, string> = {
  open: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  in_progress:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  closed:
    'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
};
