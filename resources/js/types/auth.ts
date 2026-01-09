export type User = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  phone: string;
  address: string;
  wa_number: string | null;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  roles: string[];
  permissions: string[];
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
};

export type Auth = {
  user: User;
};

/* @chisel-passkeys */
export type Passkey = {
  id: number;
  name: string;
  authenticator: string | null;
  created_at_diff: string;
  last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */
