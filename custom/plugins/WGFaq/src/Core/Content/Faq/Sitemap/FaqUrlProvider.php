<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq\Sitemap;

use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class FaqUrlProvider extends AbstractUrlProvider
{
    public function __construct(
        private readonly EntityRepository $faqRepository,
        private readonly RouterInterface $router
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'wg_faq';
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position'));
        $criteria->setLimit($limit);
        $criteria->setOffset($offset ?? 0);

        $faqs = $this->faqRepository->search($criteria, $context->getContext());

        $urls = [];
        $now = new \DateTimeImmutable();

        // Add the main FAQ page
        if ($offset === null || $offset === 0) {
            $url = new Url();
            $url->setLoc($this->router->generate('frontend.wg.faq.index'));
            $url->setLastmod($now);
            $url->setChangefreq('weekly');
            $url->setPriority(0.7);
            $url->setResource('wg_faq');
            $url->setIdentifier('faq-index');
            $urls[] = $url;
        }

        foreach ($faqs as $faq) {
            $url = new Url();
            $url->setLoc($this->router->generate('frontend.wg.faq.detail', ['faqId' => $faq->getId()]));
            $url->setLastmod($faq->getUpdatedAt() ?? $faq->getCreatedAt() ?? $now);
            $url->setChangefreq('weekly');
            $url->setPriority(0.5);
            $url->setResource('wg_faq');
            $url->setIdentifier($faq->getId());
            $urls[] = $url;
        }

        return new UrlResult($urls, $faqs->count() >= $limit ? ($offset ?? 0) + $limit : null);
    }
}
