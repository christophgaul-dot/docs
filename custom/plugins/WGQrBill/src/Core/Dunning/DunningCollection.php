<?php

declare(strict_types=1);

namespace WG\QrBill\Core\Dunning;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(DunningEntity $entity)
 * @method void              set(string $key, DunningEntity $entity)
 * @method DunningEntity[]   getIterator()
 * @method DunningEntity[]   getElements()
 * @method DunningEntity|null get(string $key)
 * @method DunningEntity|null first()
 * @method DunningEntity|null last()
 */
class DunningCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DunningEntity::class;
    }
}
