import { ACTION, GROUP } from '../../constant/kommandhub-pickup-notify-action.constant';

const { Component, Application } = Shopware;

/**
 * Register the pickup notification action with the Flow Builder service so its
 * group is known to the flow module (actionGroups is derived from
 * flowBuilderService.getGroups()). The action name/label/icon/modal are provided
 * by the component override below.
 */
Application.addServiceProviderDecorator('flowBuilderService', (flowBuilderService) => {
    flowBuilderService.addActionNames({ PICKUP_NOTIFY_ADMIN: ACTION.PICKUP_NOTIFY_ADMIN });
    flowBuilderService.addGroups({ KOMMANDHUB_CLICK_AND_PICK: GROUP });
    flowBuilderService.addActionGroupMapping({ [ACTION.PICKUP_NOTIFY_ADMIN]: GROUP });

    return flowBuilderService;
});

/**
 * Give the action its title, icon, group, configuration modal and description in
 * the Flow Builder sequence editor. Everything else falls through to the core
 * implementation via $super.
 */
Component.override('sw-flow-sequence-action', {
    computed: {
        modalName() {
            if (this.selectedAction === ACTION.PICKUP_NOTIFY_ADMIN) {
                return 'sw-flow-pickup-notify-modal';
            }

            return this.$super('modalName');
        },
    },

    methods: {
        getActionTitle(actionName) {
            if (actionName === ACTION.PICKUP_NOTIFY_ADMIN) {
                return {
                    value: actionName,
                    icon: 'regular-envelope',
                    label: this.$tc('kommandhub-pickup-notify-action.titleSendNotification'),
                    group: GROUP,
                };
            }

            return this.$super('getActionTitle', actionName);
        },

        getActionDescriptions(sequence) {
            if (sequence.actionName === ACTION.PICKUP_NOTIFY_ADMIN) {
                return this.$tc('kommandhub-pickup-notify-action.description');
            }

            return this.$super('getActionDescriptions', sequence);
        },
    },
});
