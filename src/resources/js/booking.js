/**
 * Guest booking wizard: shop & service → date & time → details → SMS code.
 * No account or cookie: the booking is created only when the SMS code is confirmed.
 * Every date/time the API sends or accepts is wall-clock time at the selected shop.
 */

const DAYS_SHOWN = 14;
const RESEND_COOLDOWN_SECONDS = 60;

export const STEPS = [
    { key: 'service', label: 'Service' },
    { key: 'time', label: 'Time' },
    { key: 'details', label: 'Details' },
    { key: 'verify', label: 'Verify' },
];

class ApiError extends Error {
    constructor(response, body) {
        const retryAfter = Number(response.headers.get('Retry-After')) || null;
        const message = response.status === 429
            ? `Too many attempts. Please try again in ${retryAfter ?? 60} seconds.`
            : body?.message || `Something went wrong (${response.status}). Please try again.`;

        super(message);
        this.status = response.status;
        this.errors = body?.errors || {};
    }
}

async function api(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const json = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response, json);
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
        steps: STEPS,
        step: 'service', // service → time → details → verify → done
        loading: false,
        error: null,
        notice: null,

        tenants: [],
        tenant: null,
        service: null,
        staffId: null,
        date: null,
        slots: [],
        slotsLoading: false,
        slot: null,

        form: { name: '', phone: '', email: '' },
        fieldErrors: {},

        verification: null, // { id, phone, expires_in }
        code: '',
        resendIn: 0,
        resendTimer: null,

        booking: null,
        cancelUrl: null,

        async init() {
            await this.run(async () => {
                this.tenants = (await api('GET', '/api/tenants')).data;
            });

            if (initialSlug) {
                await this.selectTenant(initialSlug);
            } else if (this.tenants.length === 1) {
                await this.selectTenant(this.tenants[0].slug);
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

        get stepIndex() {
            return this.steps.findIndex((step) => step.key === this.step);
        },

        go(step) {
            this.error = null;
            this.notice = null;
            this.step = step;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        // Step 1: shop and service
        async selectTenant(slug) {
            await this.run(async () => {
                this.tenant = (await api('GET', `/api/tenants/${encodeURIComponent(slug)}`)).data;
                this.service = null;
            });
        },

        changeTenant() {
            this.tenant = null;
            this.service = null;
        },

        selectService(service) {
            this.service = service;
            this.staffId = null;
            this.go('time');
            this.selectDate(this.days.find((day) => day.open)?.iso ?? this.days[0].iso);
        },

        // Step 2: barber, date and time
        get staffForService() {
            if (!this.service) {
                return [];
            }

            return this.tenant.staff_members.filter((member) => this.service.staff_member_ids.includes(member.id));
        },

        selectStaff(id) {
            this.staffId = id;
            this.slot = null;
            this.loadSlots();
        },

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
            this.go('details');
        },

        /** The chosen time is gone: back to a fresh slot grid, keeping the reason on screen. */
        async slotTaken(message) {
            this.go('time');
            this.notice = message;
            this.slot = null;
            await this.loadSlots();
        },

        // Step 3: details → SMS code
        async requestCode() {
            this.fieldErrors = {};

            await this.run(async () => {
                try {
                    this.verification = (await api('POST', '/api/booking-requests', {
                        service_id: this.service.id,
                        staff_member_id: this.staffId,
                        start_time: this.slot.start_time,
                        name: this.form.name,
                        phone: this.form.phone,
                        email: this.form.email || null,
                    })).data;
                    this.form.phone = this.verification.phone;
                    this.code = '';
                    this.startResendCooldown();
                    this.go('verify');
                    this.$nextTick(() => this.$refs.code?.focus());
                } catch (error) {
                    if (error.status === 409) {
                        await this.slotTaken(error.message);

                        return;
                    }

                    if (error.status === 422 && error.errors.start_time) {
                        await this.slotTaken(error.errors.start_time[0]);

                        return;
                    }

                    this.fieldErrors = error.errors;
                    throw error;
                }
            });
        },

        startResendCooldown() {
            clearInterval(this.resendTimer);
            this.resendIn = RESEND_COOLDOWN_SECONDS;
            this.resendTimer = setInterval(() => {
                this.resendIn = Math.max(0, this.resendIn - 1);

                if (this.resendIn === 0) {
                    clearInterval(this.resendTimer);
                }
            }, 1000);
        },

        // Step 4: confirm the code; this is what creates the booking
        onCodeInput() {
            this.code = this.code.replace(/\D/g, '').slice(0, 6);

            if (this.code.length === 6 && !this.loading) {
                this.confirmCode();
            }
        },

        async confirmCode() {
            if (this.code.length !== 6) {
                this.error = 'Enter the 6-digit code from the SMS.';

                return;
            }

            await this.run(async () => {
                try {
                    const response = await api('POST', `/api/booking-requests/${this.verification.id}/confirm`, { code: this.code });
                    this.booking = response.data;
                    this.cancelUrl = response.meta.cancel_url;
                    clearInterval(this.resendTimer);
                    this.go('done');
                } catch (error) {
                    this.code = '';

                    if (error.status === 409) {
                        await this.slotTaken(error.message);

                        return;
                    }

                    throw error.errors.code ? new Error(error.errors.code[0]) : error;
                }
            });
        },

        // Navigation and formatting helpers
        back() {
            const previous = { time: 'service', details: 'time', verify: 'details' }[this.step];

            if (previous) {
                this.go(previous);
            }
        },

        restart() {
            clearInterval(this.resendTimer);
            Object.assign(this, {
                service: null,
                slot: null,
                booking: null,
                cancelUrl: null,
                verification: null,
                code: '',
            });
            this.go('service');
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

        initials(name) {
            return name.split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase();
        },

        price(value) {
            const amount = Number(value);

            return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
        },
    };
}
