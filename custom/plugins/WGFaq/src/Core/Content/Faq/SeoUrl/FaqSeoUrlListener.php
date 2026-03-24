<?php

declare(strict_types=1);

namespace WG\Faq\Core\Content\Faq\SeoUrl;

use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FaqSeoUrlListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'wg_faq.written' => 'onFaqWritten',
            'wg_faq_translation.written' => 'onFaqTranslationWritten',
            'wg_faq.deleted' => 'onFaqDeleted',
        ];
    }

    public function onFaqWritten(EntityWrittenEvent $event): void
    {
        $this->seoUrlUpdater->update(FaqSeoUrlRoute::ROUTE_NAME, $event->getIds());
    }

    public function onFaqTranslationWritten(EntityWrittenEvent $event): void
    {
        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            if (isset($payload['wg_faq_id'])) {
                $ids[] = $payload['wg_faq_id'];
            }
        }

        if (!empty($ids)) {
            $this->seoUrlUpdater->update(FaqSeoUrlRoute::ROUTE_NAME, $ids);
        }
    }

    public function onFaqDeleted(EntityDeletedEvent $event): void
    {
        // SEO URLs are cleaned up automatically via foreign key cascade
    }
}
