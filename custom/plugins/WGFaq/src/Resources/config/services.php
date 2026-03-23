<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use WG\Faq\Administration\Controller\FaqExportImportController;
use WG\Faq\Core\Content\Faq\FaqDefinition;
use WG\Faq\Core\Content\Faq\Aggregate\FaqTranslation\FaqTranslationDefinition;
use WG\Faq\Core\Content\Faq\SeoUrl\FaqSeoUrlListener;
use WG\Faq\Core\Content\Faq\SeoUrl\FaqSeoUrlRoute;
use WG\Faq\Core\Content\Faq\Sitemap\FaqUrlProvider;
use WG\Faq\Core\Content\Cms\FaqCmsElementResolver;
use WG\Faq\Storefront\Controller\FaqController;
use WG\Faq\Storefront\Subscriber\FaqPageSubscriber;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity definitions
    $services->set(FaqDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FaqTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    // Storefront controller
    $services->set(FaqController::class)
        ->args([
            new Reference('wg_faq.repository'),
            new Reference('Shopware\Storefront\Page\GenericPageLoader'),
        ])
        ->public()
        ->call('setContainer', [new Reference('service_container')]);

    // Page subscriber (loads FAQs on navigation/product pages)
    $services->set(FaqPageSubscriber::class)
        ->args([
            new Reference('wg_faq.repository'),
        ])
        ->tag('kernel.event_subscriber');

    // SEO URL route
    $services->set(FaqSeoUrlRoute::class)
        ->args([
            new Reference(FaqDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    // SEO URL event listener
    $services->set(FaqSeoUrlListener::class)
        ->args([
            new Reference('Shopware\Core\Content\Seo\SeoUrlUpdater'),
        ])
        ->tag('kernel.event_subscriber');

    // CMS element resolver
    $services->set(FaqCmsElementResolver::class)
        ->tag('shopware.cms.data_resolver');

    // Sitemap URL provider
    $services->set(FaqUrlProvider::class)
        ->args([
            new Reference('wg_faq.repository'),
            new Reference('router'),
        ])
        ->tag('shopware.sitemap_url_provider');

    // FAQ Export/Import API controller
    $services->set(FaqExportImportController::class)
        ->args([
            new Reference('wg_faq.repository'),
            new Reference('category.repository'),
        ])
        ->public()
        ->call('setContainer', [new Reference('service_container')]);
};
