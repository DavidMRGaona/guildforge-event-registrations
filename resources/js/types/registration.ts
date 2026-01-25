export type RegistrationState = 'pending' | 'confirmed' | 'waiting_list' | 'cancelled' | 'rejected';

export interface RegistrationConfig {
    event_id: string;
    registration_enabled: boolean;
    max_participants: number | null;
    waiting_list_enabled: boolean;
    max_waiting_list: number | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    cancellation_deadline: string | null;
    requires_confirmation: boolean;
    requires_payment: boolean;
    members_only: boolean;
    custom_fields: CustomField[];
    confirmation_message: string | null;
    // Computed fields
    current_participants: number;
    current_waiting_list: number;
    available_spots: number;
    is_open: boolean;
    is_full: boolean;
}

export interface CustomField {
    name: string;
    label: string;
    type: 'text' | 'email' | 'select' | 'checkbox' | 'textarea';
    required: boolean;
    options?: string[];
}

export interface UserRegistration {
    id: string;
    event_id: string;
    user_id: string;
    state: RegistrationState;
    state_label: string;
    state_color: string;
    position: number | null;
    form_data: Record<string, string>;
    notes: string | null;
    admin_notes: string | null;
    confirmed_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface RegistrationListItem {
    id: string;
    event_id: string;
    user_id: string;
    user_name: string | null;
    user_email: string | null;
    state: RegistrationState;
    state_label: string;
    state_color: string;
    position: number | null;
    confirmed_at: string | null;
    created_at: string | null;
}

export interface RegistrationFormData {
    form_data: Record<string, string>;
    notes?: string;
}

export interface RegistrationStatusResponse {
    data: RegistrationConfig;
}

export interface UserRegistrationResponse {
    data: UserRegistration | null;
}

export interface RegistrationResponse {
    data: UserRegistration;
    message: string;
}

export interface RegistrationErrorResponse {
    message: string;
    error: string;
}

export function getStateColor(state: RegistrationState): string {
    switch (state) {
        case 'confirmed':
            return 'green';
        case 'pending':
            return 'yellow';
        case 'waiting_list':
            return 'blue';
        case 'cancelled':
            return 'gray';
        case 'rejected':
            return 'red';
        default:
            return 'gray';
    }
}

export function isActiveState(state: RegistrationState): boolean {
    return state === 'confirmed';
}

export function isWaitingState(state: RegistrationState): boolean {
    return state === 'waiting_list' || state === 'pending';
}

export function isFinalState(state: RegistrationState): boolean {
    return state === 'cancelled' || state === 'rejected';
}
