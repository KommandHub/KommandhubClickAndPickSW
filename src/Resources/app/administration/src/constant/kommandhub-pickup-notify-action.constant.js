/**
 * Frontend constants for the Click & Pick Flow Builder actions. Each value must
 * match the backend action technical name (…Action::ACTION_NAME).
 */
export const ACTION = Object.freeze({
    PICKUP_NOTIFY_ADMIN: 'action.kommandhub.pickup.notify_admin',
    PICKUP_NOTIFY_SMS: 'action.kommandhub.pickup.notify_sms',
});

export const GROUP = 'kommandhubClickAndPick';

export default { ACTION, GROUP };
