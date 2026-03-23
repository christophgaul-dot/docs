<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use WG\Faq\Core\Content\Faq\FaqDefinition;

class FaqCmsElementResolver extends AbstractCmsElementResolver
{
    public function getType(): string
    {
        return 'wg-faq';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('media');

        // Filter by display location if configured
        $displayFilter = $config->get('displayFilter');
        if ($displayFilter && $displayFilter->getValue()) {
            $criteria->addFilter(new EqualsFilter($displayFilter->getValue(), true));
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('wg_faq_' . $slot->getUniqueIdentifier(), FaqDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $faqs = $result->get('wg_faq_' . $slot->getUniqueIdentifier());

        if ($faqs === null) {
            return;
        }

        $slot->setData(new ArrayStruct(['faqs' => $faqs->getEntities()]));
    }
}
