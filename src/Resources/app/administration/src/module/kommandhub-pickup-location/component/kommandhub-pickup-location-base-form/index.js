import template from './kommandhub-pickup-location-base-form.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    emits: [
        'sales-channel-change',
        'open-days-change',
    ],

    inject: [
        'repositoryFactory',
    ],

    props: {
        pickupLocation: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('pickupLocation', ['name', 'street', 'city', 'email', 'postalCode', 'salesChannelIds']),

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        daysOfWeek() {
            return [
                { value: 'monday', label: this.$t('kommandhub-pickup-location.baseForm.monday') },
                { value: 'tuesday', label: this.$t('kommandhub-pickup-location.baseForm.tuesday') },
                { value: 'wednesday', label: this.$t('kommandhub-pickup-location.baseForm.wednesday') },
                { value: 'thursday', label: this.$t('kommandhub-pickup-location.baseForm.thursday') },
                { value: 'friday', label: this.$t('kommandhub-pickup-location.baseForm.friday') },
                { value: 'saturday', label: this.$t('kommandhub-pickup-location.baseForm.saturday') },
                { value: 'sunday', label: this.$t('kommandhub-pickup-location.baseForm.sunday') },
            ];
        },

        timeFormatOptions() {
            return [
                { value: '24h', label: this.$tc('kommandhub-pickup-location.baseForm.labelTimeFormat24Hour') },
                { value: '12h', label: this.$tc('kommandhub-pickup-location.baseForm.labelTimeFormat12Hour') },
            ];
        },

        openingHoursOptions() {
            return this.getTimeOptions();
        },

        closingHoursOptions() {
            return this.getTimeOptions();
        },

    },

    watch: {
        'pickupLocation.timeFormat': {
            immediate: true,
            handler() {
                if (!this.pickupLocation) {
                    return;
                }

                const format = this.getStoredTimeFormat();
                this.pickupLocation.timeFormat = format;
                this.pickupLocation.openingHours = this.normalizeStoredTime(this.pickupLocation.openingHours);
                this.pickupLocation.closingHours = this.normalizeStoredTime(this.pickupLocation.closingHours);
            },
        },
    },

    methods: {
        onSalesChannelChange(salesChannel) {
            this.$emit('sales-channel-change', salesChannel);
        },

        changeOpenDays(openDays) {
            this.$emit('open-days-change', openDays);
        },

        onTimeFormatChange(value) {
            const format = this.getSupportedTimeFormat(value);
            this.pickupLocation.timeFormat = format;
        },

        onOpeningHoursChange(value) {
            this.pickupLocation.openingHours = this.normalizeStoredTime(value);
        },

        onClosingHoursChange(value) {
            this.pickupLocation.closingHours = this.normalizeStoredTime(value);
        },

        getSupportedTimeFormat(value) {
            return value === '12h' ? '12h' : '24h';
        },

        getStoredTimeFormat() {
            const value = this.pickupLocation?.openingHours || this.pickupLocation?.closingHours || null;

            if (value && /AM|PM/i.test(value)) {
                return '12h';
            }

            return this.getSupportedTimeFormat(this.pickupLocation?.timeFormat);
        },

        getTimeOptions() {
            const format = this.getSupportedTimeFormat(this.pickupLocation?.timeFormat);
            const options = [];

            for (let hour = 0; hour < 24; hour += 1) {
                for (let minute = 0; minute < 60; minute += 30) {
                    const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                    const label = this.formatTimeForDisplay(value, format);

                    options.push({
                        value,
                        label,
                    });
                }
            }

            return options;
        },

        formatTimeForDisplay(value, format = '24h') {
            const normalizedValue = this.normalizeStoredTime(value);

            if (!normalizedValue) {
                return value || '';
            }

            if (format !== '12h') {
                return normalizedValue;
            }

            const [hours, minutes] = normalizedValue.split(':').map(Number);
            const suffix = hours >= 12 ? 'PM' : 'AM';
            const normalizedHour = hours % 12 || 12;

            return `${String(normalizedHour).padStart(2, '0')}:${String(minutes).padStart(2, '0')} ${suffix}`;
        },

        normalizeStoredTime(value) {
            if (!value) {
                return null;
            }

            const timeValue = String(value).trim();

            if (!timeValue) {
                return null;
            }

            const twelveHourMatch = timeValue.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
            if (twelveHourMatch) {
                let hour = Number(twelveHourMatch[1]);
                const minutes = String(twelveHourMatch[2]).padStart(2, '0');
                const meridiem = twelveHourMatch[3].toUpperCase();

                if (meridiem === 'AM' && hour === 12) {
                    hour = 0;
                }

                if (meridiem === 'PM' && hour < 12) {
                    hour += 12;
                }

                return `${String(hour).padStart(2, '0')}:${minutes}`;
            }

            const twentyFourHourMatch = timeValue.match(/^(\d{1,2}):(\d{2})$/);
            if (twentyFourHourMatch) {
                const hour = Number(twentyFourHourMatch[1]);
                const minutes = String(twentyFourHourMatch[2]).padStart(2, '0');

                if (hour >= 0 && hour <= 23) {
                    return `${String(hour).padStart(2, '0')}:${minutes}`;
                }
            }

            return null;
        },

    },
};
