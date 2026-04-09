<?php

declare(strict_types=1);

namespace WG\QrBill\Core\Document;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use WG\QrBill\Core\QrBill\QrBillGenerator;

class DunningDocumentGenerator
{
    public function __construct(
        private readonly EntityRepository $dunningRepository,
        private readonly EntityRepository $orderRepository,
        private readonly QrBillGenerator $qrBillGenerator,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Create a dunning entry for an order.
     * Automatically determines the next dunning level.
     */
    public function createDunning(string $orderId, Context $context, ?string $salesChannelId = null): array
    {
        // Get existing dunnings for this order
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::DESCENDING));

        $existingDunnings = $this->dunningRepository->search($criteria, $context);
        $currentLevel = 0;

        if ($existingDunnings->getTotal() > 0) {
            $currentLevel = $existingDunnings->first()->getLevel();
        }

        $nextLevel = min($currentLevel + 1, 3);

        // Get fee for this level
        $feeKey = 'WGQrBill.config.dunningFee' . $nextLevel;
        $fee = (float)($this->systemConfigService->get($feeKey, $salesChannelId) ?? 0);

        $dunningDays = (int)($this->systemConfigService->get('WGQrBill.config.dunningDays', $salesChannelId) ?? 30);

        // Get order to calculate total
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('orderCustomer');
        $orderCriteria->addAssociation('billingAddress.country');
        $orderCriteria->addAssociation('currency');
        $order = $this->orderRepository->search($orderCriteria, $context)->first();

        if (!$order) {
            throw new \RuntimeException('Order not found: ' . $orderId);
        }

        $totalAmount = $order->getAmountTotal() + $fee;
        $dueDate = (new \DateTimeImmutable())->modify("+{$dunningDays} days");

        $dunningId = Uuid::randomHex();
        $this->dunningRepository->create([
            [
                'id' => $dunningId,
                'orderId' => $orderId,
                'level' => $nextLevel,
                'fee' => $fee,
                'totalAmount' => $totalAmount,
                'dueDate' => $dueDate->format('Y-m-d H:i:s'),
            ],
        ], $context);

        return [
            'id' => $dunningId,
            'level' => $nextLevel,
            'fee' => $fee,
            'totalAmount' => $totalAmount,
            'dueDate' => $dueDate->format('Y-m-d'),
        ];
    }

    /**
     * Generate a dunning PDF with QR payment slip.
     */
    public function generateDunningPdf(string $dunningId, Context $context): string
    {
        $criteria = new Criteria([$dunningId]);
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.currency');

        $dunning = $this->dunningRepository->search($criteria, $context)->first();

        if (!$dunning) {
            throw new \RuntimeException('Dunning not found: ' . $dunningId);
        }

        $order = $dunning->getOrder();
        $billingAddress = $order->getBillingAddress();
        $customerNumber = $order->getOrderCustomer()->getCustomerNumber() ?? '0';
        $invoiceNumber = $order->getOrderNumber() ?? '0';
        $currency = $order->getCurrency()?->getIsoCode() ?? 'CHF';

        return $this->qrBillGenerator->generateQrBillPdf(
            $dunning->getTotalAmount(),
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
    }
}
