<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use WG\QrBill\Administration\Controller\DunningController;
use WG\QrBill\Core\Document\DunningDocumentGenerator;
use WG\QrBill\Core\Document\InvoiceQrSubscriber;
use WG\QrBill\Core\Dunning\DunningDefinition;
use WG\QrBill\Core\QrBill\QrBillGenerator;
use WG\QrBill\Core\QrBill\QrBillTwigExtension;
use WG\QrBill\Storefront\Controller\AccountInvoiceController;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity definitions
    $services->set(DunningDefinition::class)
        ->tag('shopware.entity.definition');

    // QR Bill Generator Service
    $services->set(QrBillGenerator::class)
        ->args([
            new Reference('Shopware\Core\System\SystemConfig\SystemConfigService'),
        ]);

    // Twig Extension for QR code generation in document templates
    $services->set(QrBillTwigExtension::class)
        ->args([
            new Reference(QrBillGenerator::class),
        ])
        ->tag('twig.extension');

    // Invoice QR Subscriber (adds QR data to document templates)
    $services->set(InvoiceQrSubscriber::class)
        ->args([
            new Reference(QrBillGenerator::class),
            new Reference('order.repository'),
        ])
        ->tag('kernel.event_subscriber');

    // Dunning Document Generator
    $services->set(DunningDocumentGenerator::class)
        ->args([
            new Reference('wg_dunning.repository'),
            new Reference('order.repository'),
            new Reference(QrBillGenerator::class),
            new Reference('Shopware\Core\System\SystemConfig\SystemConfigService'),
        ]);

    // Storefront Controller – Customer account invoice download
    $services->set(AccountInvoiceController::class)
        ->args([
            new Reference('order.repository'),
            new Reference(QrBillGenerator::class),
        ])
        ->public()
        ->call('setContainer', [new Reference('service_container')]);

    // Admin Controller – Dunning management
    $services->set(DunningController::class)
        ->args([
            new Reference(DunningDocumentGenerator::class),
            new Reference('wg_dunning.repository'),
        ])
        ->public()
        ->call('setContainer', [new Reference('service_container')]);
};
