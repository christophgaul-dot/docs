<?php

declare(strict_types=1);

namespace WG\QrBill\Core\Document;

use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WG\QrBill\Core\QrBill\QrBillGenerator;

class InvoiceQrSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly QrBillGenerator $qrBillGenerator,
        private readonly EntityRepository $orderRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentTemplateRendererParameterEvent::class => 'onDocumentRendered',
        ];
    }

    /**
     * Add QR bill data to document template parameters.
     * The QR payment slip will be rendered as part of the invoice Twig template.
     */
    public function onDocumentRendered(DocumentTemplateRendererParameterEvent $event): void
    {
        $parameters = $event->getParameters();

        // Only process invoice documents
        if (!isset($parameters['config']) || !isset($parameters['order'])) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $parameters['order'];
        $orderCustomer = $order->getOrderCustomer();
        $billingAddress = $order->getBillingAddress();

        if (!$orderCustomer || !$billingAddress) {
            return;
        }

        $customerNumber = $orderCustomer->getCustomerNumber() ?? '0';
        $invoiceNumber = $order->getOrderNumber() ?? '0';
        $amount = $order->getAmountTotal();
        $currency = $order->getCurrency()?->getIsoCode() ?? 'CHF';

        // Only generate for CHF and EUR
        if (!in_array($currency, ['CHF', 'EUR'], true)) {
            return;
        }

        try {
            $qrBill = $this->qrBillGenerator->createQrBill(
                $amount,
                $currency,
                $invoiceNumber,
                $customerNumber,
                $billingAddress->getFirstName() . ' ' . $billingAddress->getLastName(),
                $billingAddress->getStreet(),
                $billingAddress->getZipcode(),
                $billingAddress->getCity(),
                $billingAddress->getCountry()?->getIso() ?? 'CH',
                $order->getSalesChannelId()
            );

            $reference = $this->qrBillGenerator->generateQrrReference($customerNumber, $invoiceNumber);

            $event->setParameters(array_merge($parameters, [
                'wgQrBillReference' => $reference,
                'wgQrBillIban' => $qrBill->getCreditorInformation()->getIban(),
                'wgQrBillAmount' => number_format($amount, 2, '.', ''),
                'wgQrBillCurrency' => $currency,
                'wgQrBillAvailable' => true,
            ]));
        } catch (\Throwable $e) {
            // QR bill generation failed – invoice should still be generated without it
        }
    }
}
