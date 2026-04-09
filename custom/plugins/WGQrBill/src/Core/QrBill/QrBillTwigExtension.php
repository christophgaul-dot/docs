<?php

declare(strict_types=1);

namespace WG\QrBill\Core\QrBill;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\QrCode\QrCode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QrBillTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly QrBillGenerator $qrBillGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wg_qr_bill_svg', [$this, 'generateQrBillSvg'], ['is_safe' => ['html']]),
            new TwigFunction('wg_qr_reference', [$this, 'generateReference']),
        ];
    }

    /**
     * Generate the QR code as SVG for embedding in invoice HTML.
     */
    public function generateQrBillSvg(
        float $amount,
        string $currency,
        string $invoiceNumber,
        string $customerNumber,
        string $debtorName,
        string $debtorStreet,
        string $debtorZip,
        string $debtorCity,
        string $debtorCountry = 'CH',
        ?string $salesChannelId = null
    ): string {
        try {
            $qrBill = $this->qrBillGenerator->createQrBill(
                $amount,
                $currency,
                $invoiceNumber,
                $customerNumber,
                $debtorName,
                $debtorStreet,
                $debtorZip,
                $debtorCity,
                $debtorCountry,
                $salesChannelId
            );

            $qrCode = $qrBill->getQrCode();

            return $qrCode->writeDataUri();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Generate a formatted QR reference number.
     */
    public function generateReference(string $customerNumber, string $invoiceNumber): string
    {
        $ref = $this->qrBillGenerator->generateQrrReference($customerNumber, $invoiceNumber);

        // Format as blocks: 2 + 5x5 digits
        return substr($ref, 0, 2) . ' '
            . substr($ref, 2, 5) . ' '
            . substr($ref, 7, 5) . ' '
            . substr($ref, 12, 5) . ' '
            . substr($ref, 17, 5) . ' '
            . substr($ref, 22, 5);
    }
}
