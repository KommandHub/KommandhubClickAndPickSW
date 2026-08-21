import enGB from './en-GB.json';
import deDE from './de-DE.json';
import frFR from './fr-FR.json';

const { Locale } = Shopware;

// Merge the Flow Builder action snippets (and the custom action-group title)
// into the admin locales. Locale.extend deep-merges, so the `sw-flow` keys add
// to core rather than replacing it.
Locale.extend('en-GB', enGB);
Locale.extend('de-DE', deDE);
Locale.extend('fr-FR', frFR);
