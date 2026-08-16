<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Resources\Config;

use Kommandhub\ClickAndPickSW\Flow\Action\SendPickupNotificationToAdminAction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

#[CoversNothing]
class ServicesConfigurationTest extends TestCase
{
    public function testRegistersPickupAdminFlowActionWithExplicitFlowTag(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../../../src/Resources/config/services.yml');
        $service = $config['services'][SendPickupNotificationToAdminAction::class] ?? null;

        static::assertIsArray($service);
        static::assertSame('@Shopware\Core\Content\Mail\Service\MailService', $service['arguments']['$mailService']);
        static::assertSame('@mail_template.repository', $service['arguments']['$mailTemplateRepository']);
        static::assertSame('@Shopware\Core\System\SystemConfig\SystemConfigService', $service['arguments']['$systemConfigService']);
        static::assertSame('@logger', $service['arguments']['$logger']);
        static::assertSame([[
            'name' => 'flow.action',
            'priority' => 500,
            'key' => SendPickupNotificationToAdminAction::ACTION_NAME,
        ]], $service['tags']);
    }
}
