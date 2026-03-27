import SalesChannelPickupLocationPlugin from "./sales-channel-pickup-location/sales-channel-pickup-location.plugin";
import SalesChannelPickupLocationInfoPlugin
    from "./sales-channel-pickup-location-info/sales-channel-pickup-location-info.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('SalesChannelPickupLocation', SalesChannelPickupLocationPlugin, '[data-sales-channel-pickup-location="true"]');
PluginManager.register('SalesChannelPickupLocationInfo', SalesChannelPickupLocationInfoPlugin, '[data-sales-channel-pickup-location-info="true"]');