<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\OrderPickupLocationWriter;
use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupSelection;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[CoversClass(OrderPickupLocationWriter::class)]
#[UsesClass(PickupSelection::class)]
class OrderPickupLocationWriterTest extends TestCase
{
    public function testPersistCreatesTheOrderPickupRecord(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = Context::createDefaultContext();

        $location = new PickupLocationEntity();
        $location->setId('fedcba9876543210fedcba9876543210');
        $pickupTime = new \DateTimeImmutable('2024-06-03 10:00:00');
        $selection = new PickupSelection($location, $pickupTime, 'Ring the bell');

        $repository
            ->expects(static::once())
            ->method('create')
            ->with(
                static::callback(static function (array $payload) use ($pickupTime): bool {
                    $row = $payload[0] ?? null;

                    return \is_array($row)
                        && isset($row['id']) && \is_string($row['id']) && \strlen($row['id']) === 32
                        && $row['orderId'] === '0123456789abcdef0123456789abcdef'
                        && $row['pickupLocationId'] === 'fedcba9876543210fedcba9876543210'
                        && $row['pickupTime'] === $pickupTime
                        && $row['comment'] === 'Ring the bell';
                }),
                $context
            );

        $record = (new OrderPickupLocationWriter($repository))
            ->persist('0123456789abcdef0123456789abcdef', $selection, $context);

        static::assertSame('0123456789abcdef0123456789abcdef', $record->getOrderId());
        static::assertSame('fedcba9876543210fedcba9876543210', $record->getPickupLocationId());
        static::assertSame($pickupTime, $record->getPickupTime());
        static::assertSame('Ring the bell', $record->getComment());
        static::assertSame($location, $record->getPickupLocation());
        static::assertSame(32, \strlen($record->getId()));
    }
}
