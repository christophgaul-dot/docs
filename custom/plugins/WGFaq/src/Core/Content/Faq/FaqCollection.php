<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void           add(FaqEntity $entity)
 * @method void           set(string $key, FaqEntity $entity)
 * @method FaqEntity[]    getIterator()
 * @method FaqEntity[]    getElements()
 * @method FaqEntity|null get(string $key)
 * @method FaqEntity|null first()
 * @method FaqEntity|null last()
 */
class FaqCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FaqEntity::class;
    }

    public function getByPosition(): self
    {
        $this->sort(function (FaqEntity $a, FaqEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    public function filterActive(): self
    {
        return $this->filter(function (FaqEntity $faq) {
            return $faq->isActive();
        });
    }
}
