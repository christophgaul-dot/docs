<?php

declare(strict_types=1);

namespace WG\QrBill\Core\QrBill;

use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QrBillTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly QrBillGenerator $qrBillGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wg_qr_bill_svg', [$this, 'generateQrBillSvg'], ['is_safe' => ['html']]),
            new TwigFunction('wg_qr_reference', [$this, 'generateReference']),
            new TwigFunction('wg_qr_bill_error', [$this, 'getLastError']),
        ];
    }

    private string $lastError = '';

    /**
     * Generate the QR code as data URI for embedding in invoice HTML.
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
        $this->lastError = '';

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

            // Validate QR bill before generating QR code
            $violations = $qrBill->getViolations();
            if ($violations->count() > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                $this->lastError = implode('; ', $errors);
                $this->logger->error('QR Bill validation failed: ' . $this->lastError);
                return '';
            }

            return $qrBill->getQrCode()->writeDataUri();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->logger->error('QR Bill generation failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return '';
        }
    }

    /**
     * Generate a formatted QR reference number.
     */
    public function generateReference(string $customerNumber, string $invoiceNumber): string
    {
        try {
            $ref = $this->qrBillGenerator->generateQrrReference($customerNumber, $invoiceNumber);

            // Format as blocks: 2 + 5x5 digits
            return substr($ref, 0, 2) . ' '
                . substr($ref, 2, 5) . ' '
                . substr($ref, 7, 5) . ' '
                . substr($ref, 12, 5) . ' '
                . substr($ref, 17, 5) . ' '
                . substr($ref, 22, 5);
        } catch (\Throwable $e) {
            $this->logger->error('QR reference generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get the last error message (for debugging in templates).
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }
}
