<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Storefront\Controller;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationCollection;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\PickupLocation\Availability\PickupLocationAvailabilityService;
use Kommandhub\ClickAndPickSW\Storefront\Controller\SalesChannelPickupLocationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SalesChannelPickupLocationController::class)]
class SalesChannelPickupLocationControllerTest extends TestCase
{
    private EntityRepository&MockObject $repository;

    private PickupLocationAvailabilityService&MockObject $availabilityService;

    private TestSalesChannelPickupLocationController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->availabilityService = $this->createMock(PickupLocationAvailabilityService::class);
        $this->controller = new TestSalesChannelPickupLocationController($this->repository, $this->availabilityService);
    }

    public function testIndexFiltersByRelationalScheduleAndAvailability(): void
    {
        $open = new PickupLocationEntity();
        $open->setId('11111111111111111111111111111111');
        $closed = new PickupLocationEntity();
        $closed->setId('22222222222222222222222222222222');

        $collection = new PickupLocationCollection([$open, $closed]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($collection);

        $context = $this->salesChannelContext();

        $this->repository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();
                    $associations = $criteria->getAssociations();

                    // No JSON ContainsFilter; relational active + sales channel
                    // filters and the schedule associations loaded.
                    $fields = array_map(
                        static fn ($filter): ?string => $filter instanceof EqualsFilter ? $filter->getField() : null,
                        $filters
                    );

                    return \in_array('active', $fields, true)
                        && \in_array('salesChannels.id', $fields, true)
                        && array_key_exists('openingHoursSchedule', $associations)
                        && array_key_exists('specialHours', $associations);
                }),
                $context->getContext()
            )
            ->willReturn($searchResult);

        // Listing filters to locations open *today* (selectable), not open-now.
        $this->availabilityService
            ->expects(static::once())
            ->method('filterOpenOnDate')
            ->with([$open, $closed])
            ->willReturn([$open]);

        $response = $this->controller->index('sales-channel-id', $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            '@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-select-option.html.twig',
            $this->controller->lastTemplate
        );
        static::assertSame([$open], $this->controller->lastParameters['locations']);
    }

    public function testShowLoadsScheduleAssociationsForSingleLocation(): void
    {
        $location = new PickupLocationEntity();
        $location->setId('11111111111111111111111111111111');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($location);
        $context = $this->salesChannelContext();

        $this->repository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $associations = $criteria->getAssociations();

                    return $criteria->getIds() === ['location-id']
                        && $criteria->getLimit() === 1
                        && array_key_exists('openingHoursSchedule', $associations)
                        && array_key_exists('specialHours', $associations);
                }),
                $context->getContext()
            )
            ->willReturn($searchResult);

        $response = $this->controller->show('location-id', 'sales-channel-id', $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            '@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-field-info.html.twig',
            $this->controller->lastTemplate
        );
        static::assertSame($location, $this->controller->lastParameters['location']);
    }

    public function testShowRendersNullWhenLocationCannotBeFound(): void
    {
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn(null);
        $context = $this->salesChannelContext();

        $this->repository
            ->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $response = $this->controller->show('missing-location-id', 'sales-channel-id', $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertNull($this->controller->lastParameters['location']);
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}

class TestSalesChannelPickupLocationController extends SalesChannelPickupLocationController
{
    public string $lastTemplate = '';

    /**
     * @var array<string, mixed>
     */
    public array $lastParameters = [];

    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->lastTemplate = $view;
        $this->lastParameters = $parameters;

        return new Response('rendered');
    }
}
