export type ReportPeriodFilters = {
  period_type: 'month' | 'quarter' | 'year' | 'custom';
  month: string;
  quarter: number;
  year: number;
  from: string;
  to: string;
  label: string;
};

export const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
