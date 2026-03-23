<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use WG\Faq\Core\Content\Faq\Aggregate\FaqTranslation\FaqTranslationDefinition;

class FaqDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wg_faq';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FaqEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FaqCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),
            (new BoolField('show_on_home', 'showOnHome'))->addFlags(new ApiAware()),
            (new BoolField('show_on_category', 'showOnCategory'))->addFlags(new ApiAware()),
            (new BoolField('show_on_product', 'showOnProduct'))->addFlags(new ApiAware()),
            (new BoolField('show_on_faq_page', 'showOnFaqPage'))->addFlags(new ApiAware()),
            (new JsonField('category_ids', 'categoryIds'))->addFlags(new ApiAware()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new StringField('video_url', 'videoUrl', 1024))->addFlags(new ApiAware()),
            (new StringField('video_type', 'videoType', 32))->addFlags(new ApiAware()),

            // Translated fields
            (new TranslatedField('question'))->addFlags(new ApiAware()),
            (new TranslatedField('answer'))->addFlags(new ApiAware()),
            (new TranslatedField('seoUrl'))->addFlags(new ApiAware()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            (new TranslationsAssociationField(FaqTranslationDefinition::class, 'wg_faq_id'))->addFlags(new Required()),
        ]);
    }
}
