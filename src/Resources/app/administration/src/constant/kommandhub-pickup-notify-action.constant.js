/**
 * Frontend constants for the "Send Pickup Notification to Admin" Flow Builder
 * action. ACTION.PICKUP_NOTIFY_ADMIN must match the backend action technical
 * name (SendPickupNotificationToAdminAction::ACTION_NAME).
 */
export const ACTION = Object.freeze({
    PICKUP_NOTIFY_ADMIN: 'action.kommandhub.pickup.notify_admin',
});

export const GROUP = 'kommandhubClickAndPick';

export default { ACTION, GROUP };
