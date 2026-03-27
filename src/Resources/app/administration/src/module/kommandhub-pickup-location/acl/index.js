Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'kommandhub_pickup_location',
        roles: {
            viewer: {
                privileges: [
                    'kommandhub_pickup_location:read',
                ],
                dependencies: [
                    'sales_channel.viewer'
                ]
            },
            editor: {
                privileges: [
                    'kommandhub_pickup_location:update',
                ],
                dependencies: [
                    'kommandhub_pickup_location.viewer'
                ]
            },
            creator: {
                privileges: [
                    'kommandhub_pickup_location:create',
                ],
                dependencies: [
                    'kommandhub_pickup_location.viewer',
                    'kommandhub_pickup_location.editor'
                ]
            },
            deleter: {
                privileges: [
                    'kommandhub_pickup_location:delete',
                ],
                dependencies: [
                    'kommandhub_pickup_location.viewer'
                ]
            }
        }
    });