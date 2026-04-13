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
        $output->getPaymentPart();

        return $tcPdf->Output('', 'S');
    }

    /**
     * Generate a complete dunning PDF with letter content + QR payment slip.
     */
    public function generateDunningPdf(
        int $level,
        float $orderAmount,
        float $fee,
        float $totalAmount,
        string $currency,
        string $invoiceNumber,
        string $customerNumber,
        string $debtorName,
        string $debtorStreet,
        string $debtorZip,
        string $debtorCity,
        string $debtorCountry,
        \DateTimeInterface $dueDate,
        ?string $salesChannelId = null
    ): string {
        $qrBill = $this->createQrBill(
            $totalAmount,
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

        $config = $this->getConfig($salesChannelId);

        $tcPdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $tcPdf->setPrintHeader(false);
        $tcPdf->setPrintFooter(false);
        $tcPdf->SetMargins(20, 20, 20);
        $tcPdf->SetAutoPageBreak(false, 0);
        $tcPdf->AddPage();

        // Sender (small line above recipient)
        $tcPdf->SetFont('helvetica', '', 8);
        $senderLine = $config['creditorName'] . ' · ' . $config['creditorStreet'] . ' · '
            . $config['creditorZip'] . ' ' . $config['creditorCity'];
        $tcPdf->SetXY(20, 50);
        $tcPdf->Cell(100, 4, $senderLine, 'B', 1, 'L');

        // Recipient address
        $tcPdf->SetFont('helvetica', '', 11);
        $tcPdf->SetXY(20, 56);
        $tcPdf->MultiCell(80, 5, $debtorName . "\n" . $debtorStreet . "\n" . $debtorZip . ' ' . $debtorCity, 0, 'L');

        // Date right-aligned
        $tcPdf->SetFont('helvetica', '', 10);
        $tcPdf->SetXY(140, 56);
        $tcPdf->Cell(50, 5, $config['creditorCity'] . ', ' . (new \DateTime())->format('d.m.Y'), 0, 1, 'R');

        // Subject
        $tcPdf->SetFont('helvetica', 'B', 13);
        $tcPdf->SetXY(20, 95);
        $subject = $level === 1 ? 'Zahlungserinnerung'
            : ($level === 2 ? '2. Mahnung' : 'Letzte Mahnung');
        $tcPdf->Cell(0, 7, $subject . ' zur Rechnung Nr. ' . $invoiceNumber, 0, 1, 'L');

        // Salutation
        $tcPdf->SetFont('helvetica', '', 10);
        $tcPdf->SetXY(20, 108);
        $tcPdf->Cell(0, 5, 'Sehr geehrte Damen und Herren', 0, 1, 'L');

        // Body text per level
        $bodyTexts = [
            1 => "vermutlich ist es Ihrer Aufmerksamkeit entgangen, dass die untenstehende Rechnung noch offen ist. Wir bitten Sie, den Betrag bis zum {dueDate} auf unser Konto zu überweisen.\n\nFalls sich Ihre Zahlung mit diesem Schreiben gekreuzt hat, betrachten Sie diese Erinnerung bitte als gegenstandslos.",
            2 => "trotz unserer Zahlungserinnerung haben wir bisher keinen Zahlungseingang feststellen können. Wir bitten Sie nun dringend, den ausstehenden Betrag inkl. Mahngebühr bis zum {dueDate} zu begleichen.",
            3 => "trotz mehrfacher Mahnung ist Ihre Rechnung weiterhin offen. Bitte begleichen Sie den Gesamtbetrag inkl. Mahngebühren bis spätestens {dueDate}. Andernfalls sehen wir uns gezwungen, weitere rechtliche Schritte einzuleiten.",
        ];
        $body = str_replace('{dueDate}', $dueDate->format('d.m.Y'), $bodyTexts[$level] ?? $bodyTexts[3]);

        $tcPdf->SetXY(20, 118);
        $tcPdf->MultiCell(170, 5, $body, 0, 'L');

        // Amount table
        $tableY = $tcPdf->GetY() + 8;
        $tcPdf->SetXY(20, $tableY);
        $tcPdf->SetFont('helvetica', 'B', 10);
        $tcPdf->Cell(110, 7, 'Position', 'B', 0, 'L');
        $tcPdf->Cell(60, 7, 'Betrag (' . $currency . ')', 'B', 1, 'R');

        $tcPdf->SetFont('helvetica', '', 10);
        $tcPdf->SetX(20);
        $tcPdf->Cell(110, 6, 'Rechnung Nr. ' . $invoiceNumber, 0, 0, 'L');
        $tcPdf->Cell(60, 6, number_format($orderAmount, 2, '.', "'"), 0, 1, 'R');

        if ($fee > 0) {
            $tcPdf->SetX(20);
            $tcPdf->Cell(110, 6, 'Mahngebühr', 0, 0, 'L');
            $tcPdf->Cell(60, 6, number_format($fee, 2, '.', "'"), 0, 1, 'R');
        }

        $tcPdf->SetX(20);
        $tcPdf->SetFont('helvetica', 'B', 10);
        $tcPdf->Cell(110, 7, 'Gesamtbetrag', 'T', 0, 'L');
        $tcPdf->Cell(60, 7, number_format($totalAmount, 2, '.', "'"), 'T', 1, 'R');

        // Closing
        $tcPdf->Ln(8);
        $tcPdf->SetFont('helvetica', '', 10);
        $tcPdf->SetX(20);
        $tcPdf->MultiCell(170, 5, "Bitte verwenden Sie für die Zahlung den unten beigefügten QR-Zahlschein.\n\nFreundliche Grüsse\n" . $config['creditorName'], 0, 'L');

        // QR payment slip at bottom of A4
        $output = new TcPdfOutput($qrBill, 'de', $tcPdf);
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

        // Payment reference – auto-detect IBAN type
        $iban = $this->cleanIban($config['qrIban']);
        $referenceType = $config['referenceType'] ?? 'QRR';

        // QRR requires QR-IBAN (positions 5-9 = 30000-31999)
        // If normal IBAN is used, fall back to SCOR
        if ($referenceType === 'QRR' && !$this->isQrIban($iban)) {
            $referenceType = 'SCOR';
        }

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
     * Check if IBAN is a QR-IBAN (positions 5-9 between 30000-31999).
     */
    private function isQrIban(string $iban): bool
    {
        $iban = str_replace(' ', '', $iban);
        if (strlen($iban) < 9) {
            return false;
        }

        $iid = (int)substr($iban, 4, 5);

        return $iid >= 30000 && $iid <= 31999;
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
