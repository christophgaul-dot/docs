<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq\SeoUrl;

use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use WG\Faq\Core\Content\Faq\FaqDefinition;
use WG\Faq\Core\Content\Faq\FaqEntity;

class FaqSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.wg.faq.detail';
    public const DEFAULT_TEMPLATE = 'faq/{{ faq.translated.seoUrl|default(faq.translated.question|lower|replace({" ": "-"})) }}';

    public function __construct(
        private readonly FaqDefinition $faqDefinition
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->faqDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addAssociation('translations');
    }

    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        /** @var FaqEntity $entity */
        return new SeoUrlMapping(
            $entity,
            ['faqId' => $entity->getId()],
            ['faq' => $entity]
        );
    }
}
