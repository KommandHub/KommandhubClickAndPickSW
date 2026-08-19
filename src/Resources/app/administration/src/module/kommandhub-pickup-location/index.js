const { Component, Module, Application } = Shopware;

import './acl';

import defaultSearchConfiguration from './default-search-configuration';

// Register base form component for pickup locations
Component.register(
    'kommandhub-pickup-location-base-form',
    () => import('./component/kommandhub-pickup-location-base-form')
);

// Schedule editor (timezone, weekly opening intervals, special-date overrides)
Component.register(
    'kommandhub-pickup-location-schedule',
    () => import('./component/kommandhub-pickup-location-schedule')
);

// Register page components for listing and creating pickup locations
Component.register(
    'kommandhub-pickup-location-list',
    () => import('./page/kommandhub-pickup-location-list')
);
Component.register(
    'kommandhub-pickup-location-create',
    () => import('./page/kommandhub-pickup-location-create')
);

/**
 * Registers the kommandhub-pickup-location module in Shopware Administration.
 *
 * Features:
 * - List, create, and edit pickup locations
 * - Navigation entry under Content
 * - Access control via privileges
 *
 * Routes:
 * - index: List all pickup locations
 * - create: Create a new pickup location
 * - edit: Edit an existing pickup location by ID
 */
Module.register('kommandhub-pickup-location', {
    type: 'plugin',
    name: 'KommandhubPickupLocation',
    title: 'kommandhub-pickup-location.general.mainMenuItemGeneral',
    description: 'kommandhub-pickup-location.general.mainMenuItemGeneralDescription',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff3d58',
    icon: 'regular-map-marker',
    entity: 'kommandhub_pickup_location',

    routes: {
        index: {
            components: {
                default: 'kommandhub-pickup-location-list',
            },
            path: 'index',
            meta: {
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'kommandhub-pickup-location-create',
            path: 'create',
            meta: {
                parentPath: 'kommandhub.pickup.location.index',
            },
        },

        edit: {
            component: 'kommandhub-pickup-location-create',
            path: 'edit/:id',
            meta: {
                parentPath: 'kommandhub.pickup.location.index',
            },
            // Pass the pickupLocationId prop to the component based on the route param
            props: {
                default: ($route) => ({
                    pickupLocationId: $route.params.id.toLowerCase(),
                }),
            },
        },
    },

    navigation: [
        {
            id: 'kommandhub-pickup-location',
            label: 'kommandhub-pickup-location.general.mainMenuItemGeneral',
            color: '#ff3d58',
            path: 'kommandhub.pickup.location.index',
            icon: 'regular-map-marker',
            parent: 'sw-content',
            privilege: 'kommandhub_pickup_location.viewer',
            position: 3,
        },
    ],

    defaultSearchConfiguration
});

Application.addServiceProviderDecorator('searchTypeService', searchTypeService => {
    searchTypeService.upsertType('kommandhub_pickup_location', {
        entityName: 'kommandhub_pickup_location',
        placeholderSnippet: 'kommandhub-pickup-location.general.placeholderSearchBar',
        listingRoute: 'kommandhub.pickup.location.index',
        hideOnGlobalSearchBar: false,
    });

    return searchTypeService;
});