<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Flow\Sms;

/**
 * Structural description of the SMS gateway exposed by the optional
 * KommandhubSmsSW plugin (Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface).
 *
 * This plugin does NOT depend on KommandhubSmsSW (not in composer). The gateway
 * is injected as an optional service and used purely by duck typing; this
 * interface exists only so the call sites are statically typed. Nothing here
 * implements it — do not type-hint container services against it.
 */
interface SmsGateway
{
    public function isConfigured(?string $salesChannelId = null): bool;

    public function send(string $recipient, string $body, ?string $salesChannelId = null): ?string;
}
