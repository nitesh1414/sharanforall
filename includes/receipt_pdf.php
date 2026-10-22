<?php
/**
 * Sharan Foundation — PDF Donation Receipt Generator
 *
 * Uses bundled FPDF (includes/FPDF/fpdf.php) — zero external deps.
 * Generates a professional, branded receipt with 80G / Gift Aid info.
 */

require_once __DIR__ . '/bootstrap.php';
acts_load_fpdf();

class ActsReceiptPDF extends FPDF
{
    public $org_name      = 'Sharan Foundation';
    public $org_tagline   = 'Hope . Care . Transformation';
    public $org_addr      = 'Hyderabad, Telangana, India';
    public $org_email     = 'india@sharanforall.org';
    public $org_phone     = '+91 98765 43210';
    public $reg_80g       = 'AAATA1234A';   // sample — replace with real
    public $charity_no_uk = '1234567';      // sample — replace with real

    // PRIMARY brand colour (deep blue)
    public $brand = [37, 99, 235];
    // ACCENT (warm amber - kept the same)
    public $accent = [244, 162, 97];

    function Header()
    {
        // Coloured top band
        $this->SetFillColor(...$this->brand);
        $this->Rect(0, 0, 210, 28, 'F');

        // Org name + tagline
        $this->SetTextColor(255);
        $this->SetFont('Helvetica', 'B', 18);
        $this->SetXY(15, 8);
        $this->Cell(0, 8, $this->org_name, 0, 1);
        $this->SetXY(15, 17);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, $this->org_tagline, 0, 1);

        // Right side: Receipt label
        $this->SetXY(140, 8);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(...$this->accent);
        $this->Cell(55, 8, 'DONATION RECEIPT', 0, 0, 'R');

        // Reset text colour
        $this->SetTextColor(40);
        $this->Ln(20);
    }

    function Footer()
    {
        $this->SetY(-22);
        $this->SetDrawColor(220);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(120);
        $this->Cell(0, 4, $this->org_name . ' | ' . $this->org_addr . ' | ' . $this->org_email . ' | ' . $this->org_phone, 0, 1, 'C');
        $this->Cell(0, 4, 'Serving with love in India and the United Kingdom', 0, 1, 'C');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' | Generated ' . date('Y-m-d H:i'), 0, 0, 'C');
    }

    /** Coloured section heading */
    function SectionHeading($title)
    {
        $this->Ln(4);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...$this->brand);
        $this->Cell(0, 7, $title, 0, 1);
        $this->SetDrawColor(...$this->accent);
        $this->SetLineWidth(0.5);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 50, $this->GetY());
        $this->Ln(3);
        $this->SetTextColor(40);
        $this->SetLineWidth(0.2);
    }

    /** Key-value row */
    function Field($label, $value)
    {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(110);
        $this->Cell(55, 6, $label . ':', 0, 0);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(40);
        $this->MultiCell(0, 6, $this->fix($value), 0, 'L');
    }

    /** UTF-8 to Latin-1 because FPDF core fonts don't support unicode. */
    function fix($s) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$s);
    }
}

/**
 * Build the PDF and return the file path on disk.
 *
 * @param array $donation  Full donation row (with receipt_number set)
 * @return string Absolute path to the generated PDF file
 */
function generate_receipt_pdf($donation)
{
    $pdf = new ActsReceiptPDF('P', 'mm', 'A4');

    // Pull org settings from DB if available
    global $pdo;
    try {
        $s = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
        if ($s) {
            $pdf->org_name  = $s['site_title']  ?: $pdf->org_name;
            $pdf->org_email = $s['email_in']    ?: $pdf->org_email;
            $pdf->org_phone = $s['phone_in']    ?: $pdf->org_phone;
            $pdf->org_addr  = $s['address_in']  ?: $pdf->org_addr;
        }
    } catch (Throwable $e) {}

    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $sym = $donation['currency'] === 'INR' ? 'Rs. ' : ($donation['currency'] === 'GBP' ? 'GBP ' : '$');
    $amount = $sym . number_format((float)$donation['amount'], 2);
    $rcpt_no = $donation['receipt_number'] ?: ('AF-' . date('Y') . '-' . str_pad($donation['id'] ?? 0, 4, '0', STR_PAD_LEFT));

    // ---------- Receipt header box ----------
    $pdf->SetFillColor(255, 250, 240); // light amber
    $pdf->SetDrawColor(244, 162, 97);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(15, $pdf->GetY(), 180, 32, 'DF');
    $pdf->SetLineWidth(0.2);

    $start_y = $pdf->GetY();
    $pdf->SetXY(22, $start_y + 4);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(120);
    $pdf->Cell(80, 5, $pdf->fix('Receipt Number'), 0, 0);
    $pdf->Cell(80, 5, $pdf->fix('Receipt Date'), 0, 1, 'R');

    $pdf->SetXY(22, $start_y + 10);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(80, 7, $pdf->fix($rcpt_no), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(40);
    $pdf->Cell(80, 7, $pdf->fix($donation['payment_date'] ?: date('Y-m-d')), 0, 1, 'R');

    $pdf->SetXY(22, $start_y + 19);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(120);
    $pdf->Cell(80, 5, $pdf->fix('Amount Received'), 0, 0);
    $pdf->Cell(80, 5, $pdf->fix('Donation Type'), 0, 1, 'R');

    $pdf->SetXY(22, $start_y + 25);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(80, 7, $pdf->fix($amount), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(40);
    $pdf->Cell(80, 7, $pdf->fix(ucfirst($donation['donation_type'])), 0, 1, 'R');

    $pdf->SetY($start_y + 36);

    // ---------- Donor details ----------
    $pdf->SectionHeading('Donor Information');
    $pdf->Field('Name',      $donation['is_anonymous'] ? 'Anonymous Donor' : $donation['donor_name']);
    $pdf->Field('Email',     $donation['email']);
    if (!empty($donation['phone']))   $pdf->Field('Phone', $donation['phone']);
    if (!empty($donation['pan_number'])) $pdf->Field('PAN Number', strtoupper($donation['pan_number']));

    $addr_parts = array_filter([$donation['address'] ?? '', $donation['city'] ?? '', $donation['state'] ?? '', $donation['pincode'] ?? '', $donation['country'] ?? '']);
    if ($addr_parts) $pdf->Field('Address', implode(', ', $addr_parts));

    // ---------- Donation details ----------
    $pdf->SectionHeading('Donation Details');
    $pdf->Field('Purpose / Cause', $donation['purpose'] ?: 'Where Most Needed');
    $pdf->Field('Payment Method',  strtoupper(str_replace('_', ' ', $donation['payment_method'])));
    if (!empty($donation['transaction_id'])) $pdf->Field('Transaction ID', $donation['transaction_id']);
    $pdf->Field('Status', strtoupper($donation['payment_status']));

    if (!empty($donation['message'])) {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->SetTextColor(91, 74, 44);
        $pdf->SetFillColor(255, 250, 240);
        $pdf->MultiCell(0, 6, '"' . $pdf->fix($donation['message']) . '"', 0, 'L', true);
        $pdf->SetTextColor(40);
    }

    // ---------- Tax / Legal notice ----------
    $pdf->Ln(5);
    $pdf->SetFillColor(245, 245, 240);
    $pdf->SetDrawColor(220);
    $pdf->Rect(15, $pdf->GetY(), 180, 22, 'DF');
    $pdf->SetX(20);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(37, 99, 235);

    if ($donation['currency'] === 'INR') {
        $pdf->Cell(0, 6, $pdf->fix('Tax Exemption Notice (India)'), 0, 1);
        $pdf->SetX(20);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(60);
        $pdf->MultiCell(170, 4.5, $pdf->fix('This donation is eligible for tax exemption under Section 80G of the Income Tax Act, 1961. Registration Number: ' . $pdf->reg_80g . '. Please retain this receipt for your tax records.'), 0, 'L');
    } else {
        $pdf->Cell(0, 6, $pdf->fix('Charitable Status (United Kingdom)'), 0, 1);
        $pdf->SetX(20);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(60);
        $pdf->MultiCell(170, 4.5, $pdf->fix('Sharan Foundation UK is a registered charity (No. ' . $pdf->charity_no_uk . '). This donation may be eligible for Gift Aid. If you are a UK taxpayer, please consider completing a Gift Aid declaration to increase your donation by 25 percent at no extra cost.'), 0, 'L');
    }

    // ---------- Thank you note ----------
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 6, $pdf->fix('Thank you for your generous gift!'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(91, 74, 44);
    $pdf->MultiCell(0, 5, $pdf->fix('"Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver." - 2 Corinthians 9:7'), 0, 'C');

    // ---------- Signature ----------
    $pdf->Ln(10);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(80);
    $pdf->Cell(95, 5, $pdf->fix('This is a computer-generated receipt'), 0, 0, 'L');
    $pdf->Cell(85, 5, $pdf->fix('Authorized Signatory'), 0, 1, 'R');
    $pdf->Cell(95, 5, $pdf->fix('and does not require a signature.'), 0, 0, 'L');
    $pdf->SetX(110);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(85, 5, $pdf->fix($pdf->org_name), 0, 1, 'R');

    // ---------- Save ----------
    $dir = __DIR__ . '/../uploads/receipts/';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . 'receipt-' . preg_replace('/[^A-Za-z0-9_-]/','', $rcpt_no) . '.pdf';
    $pdf->Output('F', $file);
    return $file;
}

/**
 * Save AND get the public URL for downloading.
 */
function generate_receipt_pdf_url($donation)
{
    $file = generate_receipt_pdf($donation);
    return BASE_URL . 'uploads/receipts/' . basename($file);
}
