<?php

declare(strict_types=1);

namespace WG\QrBill\Core\QrBill;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Sprain\SwissQrBill\DataGroup\Element\CombinedAddress;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\QrCode\QrCode;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;

class QrBillGenerator
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Generate a Swiss QR Bill payment slip as PDF string.
     *
     * @param float       $amount             Invoice amount in CHF
     * @param string      $currency           CHF or EUR
     * @param string      $invoiceNumber      Invoice number for reference generation
     * @param string      $customerNumber     Customer number for reference generation
     * @param string      $debtorName         Customer full name
     * @param string      $debtorStreet       Customer street + number
     * @param string      $debtorZip          Customer postal code
     * @param string      $debtorCity         Customer city
     * @param string      $debtorCountry      Customer country code (CH, LI, etc.)
     * @param string|null $salesChannelId     Sales channel ID for config
     * @return string     PDF binary content
     */
    public function generateQrBillPdf(
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
        $qrBill = $this->createQrBill(
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

        // Generate PDF output
        $tcPdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $tcPdf->setPrintHeader(false);
        $tcPdf->setPrintFooter(false);
        $tcPdf->AddPage();

        $output = new TcPdfOutput($qrBill, 'de', $tcPdf);
        $output->setPrintable(false);
        $output->getPaymentPart();

        return $tcPdf->Output('', 'S');
    }

    /**
     * Create a QR Bill object with all payment data.
     */
    public function createQrBill(
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
    ): QrBill {
        $config = $this->getConfig($salesChannelId);

        $qrBill = QrBill::create();

        // Creditor information (payee / Zahlungsempfänger)
        $qrBill->setCreditor(
            StructuredAddress::createWithStreet(
                $config['creditorName'],
                $this->extractStreetName($config['creditorStreet']),
                $this->extractStreetNumber($config['creditorStreet']),
                $config['creditorZip'],
                $config['creditorCity'],
                $config['creditorCountry']
            )
        );

        // Creditor account (IBAN)
        $qrBill->setCreditorInformation(
            CreditorInformation::create($this->cleanIban($config['qrIban']))
        );

        // Payment amount
        $qrBill->setPaymentAmountInformation(
            PaymentAmountInformation::create($currency, $amount)
        );

        // Payment reference
        $referenceType = $config['referenceType'] ?? 'QRR';

        if ($referenceType === 'QRR') {
            $reference = $this->generateQrrReference($customerNumber, $invoiceNumber);
            $qrBill->setPaymentReference(
                PaymentReference::create(
                    PaymentReference::TYPE_QR,
                    $reference
                )
            );
        } elseif ($referenceType === 'SCOR') {
            $reference = $this->generateScorReference($customerNumber, $invoiceNumber);
            $qrBill->setPaymentReference(
                PaymentReference::create(
                    PaymentReference::TYPE_SCOR,
                    $reference
                )
            );
        } else {
            $qrBill->setPaymentReference(
                PaymentReference::create(PaymentReference::TYPE_NON)
            );
        }

        // Debtor information (payer / Zahlungspflichtiger)
        $qrBill->setUltimateDebtor(
            StructuredAddress::createWithStreet(
                $debtorName,
                $this->extractStreetName($debtorStreet),
                $this->extractStreetNumber($debtorStreet),
                $debtorZip,
                $debtorCity,
                $debtorCountry
            )
        );

        return $qrBill;
    }

    /**
     * Generate a 27-digit QR Reference (QRR) from customer number + invoice number.
     * Format: padded customer number (10 digits) + padded invoice number (16 digits) + check digit (1 digit)
     */
    public function generateQrrReference(string $customerNumber, string $invoiceNumber): string
    {
        // Extract only digits
        $custNum = preg_replace('/\D/', '', $customerNumber);
        $invNum = preg_replace('/\D/', '', $invoiceNumber);

        // Pad to fixed lengths: 10 digits customer + 16 digits invoice = 26 digits
        $custNum = str_pad(substr($custNum, 0, 10), 10, '0', STR_PAD_LEFT);
        $invNum = str_pad(substr($invNum, 0, 16), 16, '0', STR_PAD_LEFT);

        $base = $custNum . $invNum;

        // Calculate Modulo 10 recursive check digit
        $checkDigit = $this->calculateMod10Recursive($base);

        return $base . $checkDigit;
    }

    /**
     * Generate a SCOR (Structured Creditor Reference) from customer + invoice number.
     * Format: RF + 2 check digits + reference (max 21 chars)
     */
    public function generateScorReference(string $customerNumber, string $invoiceNumber): string
    {
        $reference = preg_replace('/\D/', '', $customerNumber . $invoiceNumber);
        $reference = substr($reference, 0, 21);

        // ISO 11649 check digit calculation
        $numericRef = '';
        for ($i = 0; $i < strlen($reference); $i++) {
            $char = strtoupper($reference[$i]);
            if (ctype_alpha($char)) {
                $numericRef .= (ord($char) - 55);
            } else {
                $numericRef .= $char;
            }
        }

        // Append RF00 as numeric (R=27, F=15, 0=0, 0=0)
        $numericRef .= '271500';
        $remainder = bcmod($numericRef, '97');
        $checkDigits = str_pad((string)(98 - (int)$remainder), 2, '0', STR_PAD_LEFT);

        return 'RF' . $checkDigits . $reference;
    }

    /**
     * Modulo 10 recursive check digit (used for QRR references).
     */
    private function calculateMod10Recursive(string $number): string
    {
        $table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
        $carry = 0;

        for ($i = 0; $i < strlen($number); $i++) {
            $carry = $table[($carry + (int)$number[$i]) % 10];
        }

        return (string)((10 - $carry) % 10);
    }

    /**
     * Extract street name from "Street Number" format.
     */
    private function extractStreetName(string $streetAndNumber): string
    {
        if (preg_match('/^(.+?)\s+(\d+\w*)$/', trim($streetAndNumber), $matches)) {
            return $matches[1];
        }

        return trim($streetAndNumber);
    }

    /**
     * Extract street number from "Street Number" format.
     */
    private function extractStreetNumber(string $streetAndNumber): string
    {
        if (preg_match('/^(.+?)\s+(\d+\w*)$/', trim($streetAndNumber), $matches)) {
            return $matches[2];
        }

        return '';
    }

    /**
     * Remove spaces from IBAN.
     */
    private function cleanIban(string $iban): string
    {
        return str_replace(' ', '', trim($iban));
    }

    /**
     * Get plugin configuration.
     */
    private function getConfig(?string $salesChannelId): array
    {
        return [
            'qrIban' => $this->systemConfigService->get('WGQrBill.config.qrIban', $salesChannelId) ?? '',
            'creditorName' => $this->systemConfigService->get('WGQrBill.config.creditorName', $salesChannelId) ?? '',
            'creditorStreet' => $this->systemConfigService->get('WGQrBill.config.creditorStreet', $salesChannelId) ?? '',
            'creditorZip' => $this->systemConfigService->get('WGQrBill.config.creditorZip', $salesChannelId) ?? '',
            'creditorCity' => $this->systemConfigService->get('WGQrBill.config.creditorCity', $salesChannelId) ?? '',
            'creditorCountry' => $this->systemConfigService->get('WGQrBill.config.creditorCountry', $salesChannelId) ?? 'CH',
            'referenceType' => $this->systemConfigService->get('WGQrBill.config.referenceType', $salesChannelId) ?? 'QRR',
        ];
    }
}
