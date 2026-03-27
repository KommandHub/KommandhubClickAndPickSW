const { PluginBaseClass } = window;

export default class SalesChannelPickupLocationInfoPlugin extends PluginBaseClass {
    static options = {
        /**
         * The URL to fetch pickup locations from.
         * @type {string|null}
         */
        url: null,
    }

    /**
     * Initializes the plugin by fetching pickup locations.
     */
    init() {
        this._fetchPickupLocations();
    }

    async _fetchPickupLocations() {
        if (!this.options.url) {
            throw new Error('URL for fetching pickup locations is not defined in plugin options.');
        }

        const response = await fetch(this.options.url);

        if (!response.ok) {
            throw new Error(`Network response was not ok: ${response.statusText}`);
        }

        const data = await response.text()

        console.log('Fetched pickup locations:', data);

        this.el.innerHTML = data;
    }
}