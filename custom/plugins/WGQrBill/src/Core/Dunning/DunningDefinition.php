<?php

declare(strict_types=1);

namespace WG\QrBill\Core\Dunning;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DunningDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wg_dunning';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DunningEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DunningCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),
            (new IntField('level', 'level'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('fee', 'fee'))->addFlags(new ApiAware()),
            (new FloatField('total_amount', 'totalAmount'))->addFlags(new ApiAware()),
            (new DateTimeField('due_date', 'dueDate'))->addFlags(new ApiAware()),
            (new DateTimeField('sent_at', 'sentAt'))->addFlags(new ApiAware()),
            (new LongTextField('notes', 'notes'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
        ]);
    }
}
