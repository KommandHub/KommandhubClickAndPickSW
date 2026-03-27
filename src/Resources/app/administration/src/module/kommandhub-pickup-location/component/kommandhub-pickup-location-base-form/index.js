// Import the template for the component
import template from './kommandhub-pickup-location-base-form.html.twig';

// Destructure the mapPropertyErrors helper from Shopware's component helpers
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * kommandhub-pickup-location-base-form
 * 
 * This component represents the base form for a pickup location in the administration interface.
 * 
 * Props:
 *  - pickupLocation (Object, required): The pickup location data object.
 * 
 * Emits:
 *  - sales-channel-change: Emitted when the sales channel selection changes.
 */
export default {
    /**
     * The template for the pickup location base form component.
     */
    template,

    emits: [
        'sales-channel-change',
        'open-days-change'
    ],

    inject: [
        'repositoryFactory'
    ],

    props: {
        /**
         * The pickup location object containing form data.
         */
        pickupLocation: {
            type: Object,
            required: true,
        }
    },

    computed: {
        // Map property errors for form validation feedback
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
        }
    },

    methods: {
        /**
         * Emit the sales-channel-change event when the sales channel changes.
         * @param {Object} salesChannel - The selected sales channel ID.
         */
        onSalesChannelChange(salesChannel) {
            this.$emit('sales-channel-change', salesChannel);
        },

        changeOpenDays(openDays) {
            this.$emit('open-days-change', openDays)
        }
    },
}