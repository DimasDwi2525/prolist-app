<?php

namespace App\Libraries;

require_once app_path('Libraries/fpdf/fpdf.php');

use FPDF;
use Carbon\Carbon;

class MarketingSalesReportPdf extends FPDF
{
    protected $data;
    protected $filters;
    protected $headerInfo;

    // ==== FIXED LAYOUT CONFIG ====
    protected int $headerHeight = 65;
    protected int $tableStartY = 72;
    protected int $footerHeight = 15;
    protected int $rowHeight = 10;

    public function __construct($data, $filters)
    {
        parent::__construct('P', 'mm', [420, 594]);
        $this->data = $data;
        $this->filters = $filters;

        // IMPORTANT
        $this->SetAutoPageBreak(false);

        $this->calculateHeaderInfo();
    }

    /* ================= HEADER DATA ================= */

    protected function calculateHeaderInfo()
    {
        $quote = $booking = $inq = $sales = 0;

        foreach ($this->data as $p) {
            if ($p->quotation) {
                $quote += $p->quotation->quotation_value ?? 0;
                $inq++;
            }
            if ($p->po_number) {
                $sales++;
                $booking += $p->po_value ?? 0;
            }
        }

        $this->headerInfo = [
            'quote_amount' => $quote,
            'booking_sales' => $booking,
            'inquiry_count' => $inq,
            'sales_count' => $sales,
            'quote_percentage' => $quote > 0 ? round(($booking / $quote) * 100, 2) : 0,
            'inq_sales_percentage' => $inq > 0 ? round(($sales / $inq) * 100, 2) : 0,
        ];
    }

    /* ================= HEADER ================= */

    function Header()
    {
        // Background
        $this->SetFillColor(200, 220, 235);
        $this->Rect(0, 0, $this->GetPageWidth(), $this->headerHeight, 'F');
        $this->Line(10, $this->headerHeight, $this->GetPageWidth() - 10, $this->headerHeight);


        // Title
        $this->SetY(8);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'MARKETING & SALES REPORT', 0, 1, 'C');

        // Period
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->getPeriodText(), 0, 1, 'C');

        $y = 28;
        $this->SetFont('Arial', '', 8);

        // Left
        $this->SetXY(10, $y);
        $this->Cell(130, 5, 'Quote Amount : '.$this->formatCurrency($this->headerInfo['quote_amount']), 0, 1);
        $this->SetX(10);
        $this->Cell(130, 5, 'Booking Sales: '.$this->formatCurrency($this->headerInfo['booking_sales']), 0, 1);
        $this->SetX(10);
        $this->Cell(130, 5, 'Percentage    : '.$this->headerInfo['quote_percentage'].'%', 0, 1);

        // Center
        $this->SetXY(160, $y);
        $this->Cell(110, 5, 'No Inquiry : '.$this->headerInfo['inquiry_count'], 0, 1);
        $this->SetX(160);
        $this->Cell(110, 5, 'No Sales   : '.$this->headerInfo['sales_count'], 0, 1);
        $this->SetX(160);
        $this->Cell(110, 5, 'Percentage : '.$this->headerInfo['inq_sales_percentage'].'%', 0, 1);

        // Right - Status
        $this->SetXY(290, $y);
        $this->Cell(110, 5, 'Status Quotation:', 0, 1);

        foreach ($this->getStatusSummary() as $label => $count) {
            $this->SetX(290);
            $this->Cell(110, 5, "$label : $count", 0, 1);
        }

        // Bottom line
        $this->Line(10, $this->headerHeight, $this->GetPageWidth() - 10, $this->headerHeight);
    }

    /* ================= FOOTER ================= */

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Page '.$this->PageNo(), 0, 0, 'C');
    }

    /* ================= BUILD ================= */

    public function build()
    {
        $this->AddPage();
        $this->SetY($this->tableStartY);
        $this->drawTableHeader();
        $this->drawTableBody();
    }

    /* ================= TABLE ================= */

    protected function drawTableHeader()
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(180, 200, 220);

        // Main header
        $this->Cell(13, 10, 'No', 1, 0, 'C', true);
        $this->Cell(285, 10, 'Sales', 1, 0, 'C', true);
        $this->Cell(107, 10, 'Marketing & Sales', 1, 1, 'C', true);

        // Sub header
        $this->Cell(13, 10, '', 1, 0, 'C', true);
        $this->Cell(23, 10, 'Inquiry', 1, 0, 'C', true);
        $this->Cell(26, 10, 'Project No', 1, 0, 'C', true);
        $this->Cell(51, 10, 'Client', 1, 0, 'C', true);
        $this->Cell(39, 10, 'PIC', 1, 0, 'C', true);
        $this->Cell(32, 10, 'Contact', 1, 0, 'C', true);
        $this->Cell(77, 10, 'Project Name', 1, 0, 'C', true);
        $this->Cell(32, 10, 'Quotation', 1, 0, 'C', true);
        $this->Cell(23, 10, 'Status', 1, 0, 'C', true);
        $this->Cell(58, 10, 'PO No', 1, 0, 'C', true);
        $this->Cell(40, 10, 'PO Value', 1, 1, 'C', true);
    }

    protected function drawTableBody()
    {
        $this->SetFont('Arial', '', 9);

        $fill = false; // ZEBRA FLAG
        $rowNo = 1;

        foreach ($this->data as $p) {

            $this->checkPageBreak();

            // Zebra colors
            $this->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

            $this->Cell(13, $this->rowHeight, $rowNo++, 1, 0, 'C', true);
            $this->Cell(23, $this->rowHeight, $this->formatDate($p->quotation?->inquiry_date), 1, 0, 'C', true);
            $this->Cell(26, $this->rowHeight, $p->project_number ?? '-', 1, 0, 'C', true);
            $this->Cell(51, $this->rowHeight, $this->truncate($p->client?->name ?? $p->quotation->client->name, 100), 1, 0, 'C', true);
            $this->Cell(39, $this->rowHeight, $this->truncate($p->quotation?->client_pic, 100), 1, 0, 'C', true);
            $this->Cell(32, $this->rowHeight, $this->truncate($p->quotation?->client?->phone, 100), 1, 0, 'C', true);
            $this->Cell(77, $this->rowHeight, $this->truncate($p->project_name ?? '-', 100), 1, 0, 'C', true);
            $this->Cell(32, $this->rowHeight, $p->quotation?->no_quotation ?? '-', 1, 0, 'C', true);
            $this->Cell(23, $this->rowHeight, $this->getStatusLabel($p->quotation?->status), 1, 0, 'C', true);
            $this->Cell(58, $this->rowHeight, $this->truncate($p->po_number ?? '-', 55), 1, 0, 'C', true);
            $this->Cell(30, $this->rowHeight, $this->formatCurrency($p->po_value), 1, 1, 'C', true);



            $fill = !$fill;
        }
    }

    protected function checkPageBreak()
    {
        if ($this->GetY() + $this->rowHeight > $this->GetPageHeight() - $this->footerHeight) {
            $this->AddPage();
            $this->SetY($this->tableStartY);
            $this->drawTableHeader();
        }
    }

    /* ================= HELPERS ================= */

    protected function formatCurrency($v)
    {
        return $v ? 'Rp '.number_format($v, 0, ',', '.') : '-';
    }

    protected function formatDate($d)
    {
        return $d ? Carbon::parse($d)->format('d-m-Y') : '-';
    }

    protected function truncate($text, $len = 100)
    {
        if (!$text) return '-';
        return strlen($text) > $len ? substr($text, 0, $len - 3).'...' : $text;
    }

    protected function getStatusSummary()
    {
        $out = [];
        foreach ($this->data as $p) {
            if ($p->quotation?->status) {
                $label = $this->getStatusLabel($p->quotation->status);
                $out[$label] = ($out[$label] ?? 0) + 1;
            }
        }
        return $out;
    }

    protected function getStatusLabel($s)
    {
        return [
            'O' => 'Open',
            'A' => 'Approved',
            'R' => 'Rejected',
            'C' => 'Closed'
        ][$s] ?? $s;
    }

    protected function getPeriodText()
    {
        return 'Year '.$this->filters['year'];
    }
}
