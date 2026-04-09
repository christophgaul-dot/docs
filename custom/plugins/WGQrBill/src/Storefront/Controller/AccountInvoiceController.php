<?php

declare(strict_types=1);

namespace WG\QrBill\Storefront\Controller;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WG\QrBill\Core\QrBill\QrBillGenerator;

#[Route(defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
class AccountInvoiceController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly QrBillGenerator $qrBillGenerator
    ) {
    }

    #[Route(
        path: '/account/invoice/{orderId}/qr-download',
        name: 'frontend.account.invoice.qr.download',
        methods: ['GET']
    )]
    public function downloadQrInvoice(string $orderId, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        // Verify order belongs to this customer
        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $billingAddress = $order->getBillingAddress();
        $customerNumber = $order->getOrderCustomer()->getCustomerNumber() ?? '0';
        $invoiceNumber = $order->getOrderNumber() ?? '0';
        $currency = $order->getCurrency()?->getIsoCode() ?? 'CHF';

        $pdfContent = $this->qrBillGenerator->generateQrBillPdf(
            $order->getAmountTotal(),
            $currency,
            $invoiceNumber,
            $customerNumber,
            $billingAddress->getFirstName() . ' ' . $billingAddress->getLastName(),
            $billingAddress->getStreet(),
            $billingAddress->getZipcode(),
            $billingAddress->getCity(),
            $billingAddress->getCountry()?->getIso() ?? 'CH',
            $context->getSalesChannelId()
        );

        $filename = 'QR-Rechnung-' . $invoiceNumber . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
