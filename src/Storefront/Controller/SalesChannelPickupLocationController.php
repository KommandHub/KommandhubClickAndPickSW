<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Storefront\Controller;

use Kommandhub\ClickAndPickSW\PickupLocation\Availability\PickupLocationAvailabilityService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SalesChannelPickupLocationController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $kommandhubPickupLocationRepository,
        private readonly PickupLocationAvailabilityService $availabilityService,
    ) {
    }

    #[Route(
        path: '/kommandhub/sales-channel/{salesChannelId}/pickup-locations',
        name: 'frontend.kommandhub.sales-channel.pickup-locations.index',
        defaults: ['XmlHttpRequest' => 'true'],
        methods: ['GET']
    )]
    public function index(string $salesChannelId, SalesChannelContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        // Relational schedule loaded once; availability is evaluated in PHP so
        // each location's own timezone drives the decision (a single SQL weekday
        // filter cannot, since the local weekday differs per timezone).
        $criteria->addAssociation('salesChannels');
        $criteria->addAssociation('openingHoursSchedule');
        $criteria->addAssociation('specialHours');

        /** @var list<\Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity> $locations */
        $locations = array_values($this->kommandhubPickupLocationRepository
            ->search($criteria, $context->getContext())
            ->getEntities()
            ->getElements());

        // List everything open *today* (in each location's timezone) so a
        // customer can still choose a location that opens later today — not only
        // one open at this exact minute.
        $openLocations = $this->availabilityService->filterOpenOnDate($locations);

        return $this->renderStorefront(
            '@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-select-option.html.twig',
            ['locations' => $openLocations]
        );
    }

    #[Route(
        path: '/kommandhub/sales-channel/{salesChannelId}/location/{locationId}',
        name: 'frontend.kommandhub.sales-channel.pickup-locations.show',
        defaults: ['XmlHttpRequest' => 'true'],
        methods: ['GET']
    )]
    public function show(string $locationId, string $salesChannelId, SalesChannelContext $salesChannelContext): Response
    {
        $criteria = new Criteria([$locationId]);
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addAssociation('openingHoursSchedule');
        $criteria->addAssociation('specialHours');
        $criteria->setLimit(1);

        $location = $this->kommandhubPickupLocationRepository->search(
            $criteria,
            $salesChannelContext->getContext()
        )->first();

        return $this->renderStorefront(
            '@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-field-info.html.twig',
            ['location' => $location]
        );
    }
}
