export type WaterAccountStatus = 'active' | 'inactive';

export type SharedWaterAccount = {
  id: number;
  account_number: string;
  connection_address: string | null;
  status: WaterAccountStatus;
};
