<?php

declare(strict_types=1);

namespace WG\Faq\Storefront\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FaqPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $faqRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getContext();
        $salesChannelContext = $event->getSalesChannelContext();

        // Determine if this is the homepage or a category page
        $navigationId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $currentCategoryId = $event->getRequest()->get('navigationId', $navigationId);
        $isHome = $currentCategoryId === $navigationId;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('media');

        if ($isHome) {
            $criteria->addFilter(new EqualsFilter('showOnHome', true));
        } else {
            // Show FAQs assigned to categories - either this specific category or all categories
            $criteria->addFilter(new EqualsFilter('showOnCategory', true));
        }

        $faqs = $this->faqRepository->search($criteria, $context)->getEntities();

        // If on a category page, filter FAQs by categoryIds (if set)
        if (!$isHome) {
            $faqs = $faqs->filter(function ($faq) use ($currentCategoryId) {
                $categoryIds = $faq->getCategoryIds();
                // If no specific categories are set, show on all category pages
                if (empty($categoryIds)) {
                    return true;
                }
                return in_array($currentCategoryId, $categoryIds, true);
            });
        }

        if ($faqs->count() > 0) {
            $page->addExtension('wgFaqs', $faqs);
        }
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('showOnProduct', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('media');

        $faqs = $this->faqRepository->search($criteria, $context)->getEntities();

        if ($faqs->count() > 0) {
            $page->addExtension('wgFaqs', $faqs);
        }
    }
}
