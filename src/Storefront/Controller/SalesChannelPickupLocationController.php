<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SalesChannelPickupLocationController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $kommandhubPickupLocationRepository
    ) {}

    #[Route(
        path: '/kommandhub/sales-channel/{salesChannelId}/pickup-locations',
        name: 'frontend.kommandhub.sales-channel.pickup-locations.index',
        defaults: ['XmlHttpRequest' => 'true'],
        methods: ['GET']
    )]
    public function index(string $salesChannelId, SalesChannelContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(
            new EqualsFilter('salesChannels.id', $salesChannelId)
        );

        $locations = $this->kommandhubPickupLocationRepository->search($criteria, $context->getContext());

        return $this->renderStorefront('@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-select-option.html.twig', [
            'locations' => $locations,
        ]);
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
        $criteria->setLimit(1);

        $location = $this->kommandhubPickupLocationRepository->search(
            $criteria,
            $salesChannelContext->getContext()
        )->first();

        return $this->renderStorefront('@KommandhubClickAndPickSW/storefront/component/shipping/custom/pickup-location-field-info.html.twig', [
            'location' => $location,
        ]);
    }
}