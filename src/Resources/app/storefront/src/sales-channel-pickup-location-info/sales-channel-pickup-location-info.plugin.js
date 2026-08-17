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

        const data = await response.text();
        this.el.innerHTML = data;
        this._bindRemovePickupLocationButton();
    }

    _bindRemovePickupLocationButton() {
        const removeButton = this.el.querySelector('[data-pickup-location-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.addEventListener('click', (event) => {
            event.preventDefault();

            const select = document.querySelector('#pickup-location-location-field-id');
            const feedback = this.el.querySelector('[data-pickup-location-feedback]');

            if (!select) {
                if (feedback) {
                    feedback.textContent = 'Pickup location could not be removed. Please try again.';
                }
                return;
            }

            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));

            if (feedback) {
                feedback.textContent = 'Pickup location removed.';
            }
        });
    }
}