export type MeterReadingAccountCard = {
  id: number;
  owner_name: string;
  account_number: string;
  meter_number: string;
  latest_reading: { value: number; date: string } | null;
  read_this_month: boolean;
  current_reading_id: number | null;
};

export type MeterReadingProgress = {
  read: number;
  total: number;
};

export type MeterReadingAccountSummary = {
  id: number;
  owner_name: string;
  account_number: string;
  meter_number: string;
};

export type PreviousReading = {
  value: number;
  date: string | null;
  is_initial: boolean;
};

export type MeterReadingHistoryRow = {
  id: number;
  month: string;
  value: number;
  consumption: number;
  recorded_by: string;
  date: string;
  is_latest: boolean;
};
