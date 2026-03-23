<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq\Aggregate\FaqTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use WG\Faq\Core\Content\Faq\FaqDefinition;

class FaqTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'wg_faq_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FaqTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return FaqTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return FaqDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('question', 'question', 1024))->addFlags(new Required(), new ApiAware()),
            (new LongTextField('answer', 'answer'))->addFlags(new ApiAware()),
            (new StringField('seo_url', 'seoUrl', 512))->addFlags(new ApiAware()),
            (new StringField('meta_title', 'metaTitle', 255))->addFlags(new ApiAware()),
            (new StringField('meta_description', 'metaDescription', 512))->addFlags(new ApiAware()),
        ]);
    }
}
