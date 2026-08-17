<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Storefront\Controller;

use Kommandhub\ClickAndPickSW\Storefront\Controller\SalesChannelPickupLocationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SalesChannelPickupLocationController::class)]
class SalesChannelPickupLocationControllerTest extends TestCase
{
    private EntityRepository&MockObject $repository;

    private TestSalesChannelPickupLocationController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->controller = new TestSalesChannelPickupLocationController($this->repository);
    }

    public function testIndexRendersActiveLocationsForCurrentOpenDayAndSalesChannel(): void
    {
        $searchResult = $this->createMock(EntitySearchResult::class);
        $context = $this->salesChannelContext();
        $expectedDay = strtolower((new \DateTimeImmutable())->format('l'));

        $this->repository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria) use ($expectedDay): bool {
                    $filters = $criteria->getFilters();

                    return $criteria->getAssociation('salesChannels') !== null
                        && $filters[0]?->getField() === 'active'
                        && $filters[0]?->getValue() === true
                        && $filters[1] instanceof ContainsFilter
                        && $filters[1]?->getField() === 'openDays'
                        && $filters[1]?->getValue() === $expectedDay
                        && $filters[2]?->getField() === 'salesChannels.id'
                        && $filters[2]?->getValue() === 'sales-channel-id';
                }),
                $context->getContext()
            )
            ->willReturn($searchResult);

        $response = $this->controller->index('sales-channel-id', $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            '@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-select-option.html.twig',
            $this->controller->lastTemplate
        );
        static::assertSame($searchResult, $this->controller->lastParameters['locations']);
    }

    public function testCreateOpenDayFilterUsesMatchingAndNonMatchingDays(): void
    {
        $matchingFilter = $this->controller->createOpenDayFilterForDate(new \DateTimeImmutable('2024-01-01'));
        $nonMatchingFilter = $this->controller->createOpenDayFilterForDate(new \DateTimeImmutable('2024-01-02'));

        static::assertInstanceOf(ContainsFilter::class, $matchingFilter);
        static::assertSame('monday', $matchingFilter->getValue());
        static::assertSame('tuesday', $nonMatchingFilter->getValue());
        static::assertNotSame($matchingFilter->getValue(), $nonMatchingFilter->getValue());
    }

    public function testShowRendersSingleLocationForSalesChannel(): void
    {
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn('location-entity');
        $context = $this->salesChannelContext();

        $this->repository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();

                    return $criteria->getIds() === ['location-id']
                        && $criteria->getLimit() === 1
                        && $filters[0]?->getField() === 'salesChannels.id'
                        && $filters[0]?->getValue() === 'sales-channel-id';
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
        static::assertSame('location-entity', $this->controller->lastParameters['location']);
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

    public function createOpenDayFilterForDate(\DateTimeInterface $date): ContainsFilter
    {
        return $this->createOpenDayFilter($date);
    }

    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->lastTemplate = $view;
        $this->lastParameters = $parameters;

        return new Response('rendered');
    }
}
