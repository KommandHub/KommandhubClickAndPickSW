import template from './sw-flow-pickup-notify-modal.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * Configuration modal for the "Send Pickup Notification to Admin" action. The
 * recipient is always the order's pickup location, so the only (optional) option
 * is which mail template to use; left empty, the backend falls back to the
 * plugin's default admin pickup template.
 */
Component.register('sw-flow-pickup-notify-modal', {
    template,

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            mailTemplateId: null,
        };
    },

    computed: {
        mailTemplateCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.mailTemplateId = this.sequence?.config?.mailTemplateId ?? null;
        },

        onMailTemplateChange(id) {
            this.mailTemplateId = id ?? null;
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            this.$emit('process-finish', {
                ...this.sequence,
                config: {
                    mailTemplateId: this.mailTemplateId,
                },
            });
        },
    },
});
