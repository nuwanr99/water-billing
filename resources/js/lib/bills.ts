export type BillStatus = 'generated' | 'approved' | 'paid' | 'overdue';

export const billStatusLabels: Record<BillStatus, string> = {
  generated: 'Generated',
  approved: 'Approved',
  paid: 'Paid',
  overdue: 'Overdue',
};

export const billStatusDotClasses: Record<BillStatus, string> = {
  generated: 'bg-muted-foreground',
  approved: 'bg-sky-500',
  paid: 'bg-emerald-500',
  overdue: 'bg-red-500',
};
