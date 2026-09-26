/**
 * Booking widget state. Talks to the JSON API with the session cookie
 * (Sanctum SPA mode); every date/time the API sends or accepts is
 * wall-clock time in the selected shop's timezone.
 */

const DAYS_SHOWN = 14;

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `Request failed (${status})`);
        this.status = status;
        this.errors = body?.errors || {};
    }
}

async function api(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (response.status === 204) {
        return null;
    }

    const json = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, json);
    }

    return json;
}

/** "YYYY-MM-DD" for the current day on the shop's clock. */
function todayIn(timezone) {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

/** Calendar arithmetic on "YYYY-MM-DD" strings, free of the browser's own timezone. */
function addDays(isoDate, days) {
    const [y, m, d] = isoDate.split('-').map(Number);

    return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
}

function describeDate(isoDate) {
    const [y, m, d] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d));
    const part = (options) => new Intl.DateTimeFormat(undefined, { timeZone: 'UTC', ...options }).format(date);

    return {
        iso: isoDate,
        dayOfWeek: date.getUTCDay(), // 0 = Sunday, matching business_hours.day_of_week
        weekday: part({ weekday: 'short' }),
        day: part({ day: 'numeric' }),
        month: part({ month: 'short' }),
        long: part({ weekday: 'long', day: 'numeric', month: 'long' }),
    };
}

export default function bookingWidget(initialSlug = null) {
    return {
        step: 'shop', // shop → service → time → confirm → done
        loading: false,
        error: null,

        tenants: [],
        tenant: null,
        service: null,
        staffId: '',
        date: null,
        slots: [],
        slotsLoading: false,
        slot: null,

        user: null,
        authMode: 'login',
        form: { name: '', email: '', phone: '', password: '', password_confirmation: '' },
        fieldErrors: {},
        booking: null,

        async init() {
            this.loadUser();
            await this.run(async () => {
                this.tenants = (await api('GET', '/api/tenants')).data;
            });

            if (initialSlug) {
                await this.selectTenant(initialSlug);
            }
        },

        /** Wraps a request with the shared loading/error handling. */
        async run(callback) {
            this.loading = true;
            this.error = null;

            try {
                return await callback();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        async loadUser() {
            try {
                this.user = (await api('GET', '/api/user')).data;
            } catch {
                this.user = null;
            }
        },

        // Step 1: shop
        async selectTenant(slug) {
            await this.run(async () => {
                this.tenant = (await api('GET', `/api/tenants/${encodeURIComponent(slug)}`)).data;
                this.service = null;
                this.step = 'service';
            });
        },

        // Step 2: service and (optional) staff member
        selectService(service) {
            this.service = service;
            this.staffId = '';
            this.step = 'time';
            this.selectDate(this.days.find((day) => day.open)?.iso ?? this.days[0].iso);
        },

        get staffForService() {
            if (!this.service) {
                return [];
            }

            return this.tenant.staff_members.filter((member) => this.service.staff_member_ids.includes(member.id));
        },

        // Step 3: date strip and slot grid
        get days() {
            if (!this.tenant) {
                return [];
            }

            const openDays = new Set(this.tenant.business_hours.map((hours) => hours.day_of_week));
            const today = todayIn(this.tenant.timezone);

            return Array.from({ length: DAYS_SHOWN }, (_, offset) => {
                const day = describeDate(addDays(today, offset));

                return { ...day, open: openDays.has(day.dayOfWeek) };
            });
        },

        get selectedDay() {
            return this.days.find((day) => day.iso === this.date);
        },

        get availableSlots() {
            return this.slots.filter((slot) => slot.available);
        },

        async selectDate(isoDate) {
            this.date = isoDate;
            this.slot = null;
            await this.loadSlots();
        },

        async loadSlots() {
            this.slots = [];

            if (!this.selectedDay?.open) {
                return;
            }

            const params = new URLSearchParams({ date: this.date });

            if (this.staffId) {
                params.set('staff_member_id', this.staffId);
            }

            this.slotsLoading = true;
            this.error = null;

            try {
                const url = `/api/tenants/${this.tenant.slug}/services/${this.service.id}/availability?${params}`;
                this.slots = (await api('GET', url)).data;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.slotsLoading = false;
            }
        },

        selectSlot(slot) {
            this.slot = slot;
            this.step = 'confirm';
        },

        // Step 4: authenticate and book
        async authenticate() {
            this.fieldErrors = {};

            await this.run(async () => {
                try {
                    const payload = this.authMode === 'login'
                        ? { email: this.form.email, password: this.form.password }
                        : { ...this.form, phone: this.form.phone || null };

                    this.user = (await api('POST', `/auth/${this.authMode}`, payload)).data;
                    this.form.password = this.form.password_confirmation = '';
                } catch (error) {
                    this.fieldErrors = error.errors ?? {};
                    throw error;
                }
            });
        },

        async logout() {
            await this.run(async () => {
                await api('POST', '/auth/logout');
                this.user = null;
            });
        },

        async confirmBooking() {
            await this.run(async () => {
                try {
                    this.booking = (await api('POST', '/api/bookings', {
                        service_id: this.service.id,
                        staff_member_id: this.staffId ? Number(this.staffId) : null,
                        start_time: this.slot.start_time,
                    })).data;
                    this.step = 'done';
                } catch (error) {
                    if (error.status === 401) {
                        this.user = null;
                    } else if (error.status === 409 || error.status === 422) {
                        // Someone else took the slot meanwhile: show fresh availability.
                        this.step = 'time';
                        await this.loadSlots();
                    }

                    throw error;
                }
            });
        },

        // Navigation and formatting helpers
        back() {
            this.error = null;
            this.step = { service: 'shop', time: 'service', confirm: 'time' }[this.step] ?? 'shop';
        },

        restart() {
            Object.assign(this, { step: 'shop', tenant: null, service: null, slot: null, booking: null, error: null });
        },

        time(dateTime) {
            return dateTime.slice(11, 16);
        },

        longDate(dateTime) {
            return describeDate(dateTime.slice(0, 10)).long;
        },

        staffName(id) {
            return this.tenant?.staff_members.find((member) => member.id === id)?.name;
        },
    };
}
