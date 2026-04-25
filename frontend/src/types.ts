export type LookupResponse = {
  found: boolean;
  participant_id?: number;
  qr?: string;
  name?: string;
  county?: string;
  type?: string;
  checked_in?: boolean;
  session_id?: number;
  slot?: string;
  screenfree_seconds?: number;
};

export type CheckinResponse = {
  success: boolean;
  error?: string;
  slot?: string;
  name?: string;
  checked_in_at?: string;
  session_token?: string;
  checkout_qr_text?: string;
};

export type SearchItem = {
  id: number;
  name?: string;
  first_name?: string;
  last_name?: string;
  county: string;
  participant_type: string;
  qr_code: string;
};

export type ActiveItem = {
  session_id: number;
  slot_id: number;
  slot_number: string;
  slot_type: 'storage' | 'charging';
  qr_code: string;
  name: string;
  checkin_time: string;
};

export type SlotItem = {
  slot_id: number;
  slot_number: string;
  slot_type: 'storage' | 'charging';
  is_active: number | string;
  session_id?: number;
  qr_code?: string;
  name?: string;
  status: 'free' | 'busy' | 'disabled';
};

export type SlotDetail = {
  slot_id: number;
  slot_number: string;
  slot_type: 'storage' | 'charging';
  is_active: number | string;
  session_id?: number;
  checkin_time?: string;
  qr_code?: string;
  name?: string;
  status: 'free' | 'busy' | 'disabled';
};