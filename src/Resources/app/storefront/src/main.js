import SalesChannelPickupLocationPlugin from "./sales-channel-pickup-location/sales-channel-pickup-location.plugin";
import SalesChannelPickupTimePlugin from "./sales-channel-pickup-time/sales-channel-pickup-time.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('SalesChannelPickupLocation', SalesChannelPickupLocationPlugin, '[data-sales-channel-pickup-location="true"]');
PluginManager.register('SalesChannelPickupTime', SalesChannelPickupTimePlugin, '[data-sales-channel-pickup-time="true"]');