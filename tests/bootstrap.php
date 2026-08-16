<?php

// If running in CI → skip Shopware bootstrap (unit tests need no kernel)
if (getenv('CI') === 'true') {
    require __DIR__ . '/../vendor/autoload.php';

    return;
}

// Otherwise (local dev) → use the Shopware test bootstrap
require __DIR__ . '/TestBootstrapper.php';
require __DIR__ . '/TestBootstrap.php';
