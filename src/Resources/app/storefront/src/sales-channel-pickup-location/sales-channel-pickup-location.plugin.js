const { PluginBaseClass } = window;

export default class SalesChannelPickupLocationPlugin extends PluginBaseClass {
    /**
     * Plugin for fetching and populating pickup locations in a select field.
     */
    static options = {
        /**
         * The URL to fetch pickup locations from.
         * @type {string|null}
         */
        url: null,

        /**
         * CSS selector for the select element to populate.
         * @type {string}
         */
        selectorSelectOptions: '#pickup-location-location-field-id',

        /**
         * The currently selected pickup location ID.
         * @type {string|null}
         */
        selectedPickupLocationId: null,
    };

    /**
     * Initializes the plugin by fetching pickup locations.
     */
    init() {
        this._fetchPickupLocations();
    }

    /**
     * Fetches pickup location options HTML and injects it into the select element.
     * After injection, it refreshes the rendered location info.
     *
     * @returns {Promise<void>}
     * @private
     */
    async _fetchPickupLocations() {
        if (!this.options.url) {
            console.error('SalesChannelPickupLocationPlugin: URL option is not set.');
            return;
        }

        try {
            const response = await fetch(this.options.url);
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.statusText}`);
            }
            const data = await response.text();

            const container = this.el.querySelector(this.options.selectorSelectOptions);

            if (!container) {
                console.error(`SalesChannelPickupLocationPlugin: Container with selector ${this.options.selectorSelectOptions} not found.`);
                return;
            }

            container.innerHTML = data;
            this._updateSelectedPickupLocationOptionData(container);
        } catch (error) {
            console.error('There was a problem with the fetch operation:', error);
        }
    }

    /**
     * Re-applies the previously selected pickup location to the freshly injected
     * option list, if that option is still present.
     *
     * @param {HTMLSelectElement} select
     * @private
     */
    _updateSelectedPickupLocationOptionData(select) {
        const selectedId = this.options.selectedPickupLocationId;

        if (!selectedId || !select) {
            return;
        }

        const hasOption = Array.from(select.options).some((option) => option.value === selectedId);

        if (hasOption) {
            select.value = selectedId;
        }
    }
}