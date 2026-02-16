<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Libraries\MarketingSalesReportPdf;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalesReportApiController extends Controller
{
    public function index(Request $request)
    {
        $yearParam = $request->query('year');
        $availableYears = $this->getYears();
        $year = $yearParam ? (int)$yearParam : (!empty($availableYears) ? end($availableYears) : now()->year);

        $rangeType = $request->query('range_type', 'monthly'); // monthly, weekly, custom
        $monthParam = $request->query('month'); // 1-12
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Inisialisasi query
        $projects = Project::with(['category', 'quotation.client', 'client', 'statusProject']);

        // Filter berdasarkan range type
        if ($rangeType === 'monthly' && $monthParam) {
            $projects->whereYearFromPn($year)
                    ->whereMonth('po_date', $monthParam);
        } elseif ($rangeType === 'weekly') {
            $projects->whereBetween('po_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($rangeType === 'custom' && $fromDate && $toDate) {
            $projects->whereBetween('po_date', [$fromDate, $toDate]);
        } else {
            // default: filter berdasarkan tahun saja
            $projects->whereYearFromPn($year);
        }

        $projects = $projects
            ->orderByRaw('CAST(LEFT(CAST(pn_number AS VARCHAR), 2) AS INT) DESC') // ambil 2 digit pertama sebagai tahun
            ->orderByRaw('CAST(SUBSTRING(CAST(pn_number AS VARCHAR), 3, LEN(CAST(pn_number AS VARCHAR)) - 2) AS INT) DESC') // ambil nomor urut
            ->get();

        // Calculate total project value from filtered projects
        $totalProjectValue = $projects->sum(function ($project) {
            return $project->quotation ? $project->quotation->quotation_value : 0;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales report fetched successfully',
            'data'    => $projects,
            'totalProjectValue' => $totalProjectValue,
            'filters' => [
                'year' => $year,
                'range_type' => $rangeType,
                'month' => $monthParam,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'available_years' => $availableYears,
            ],
        ]);
    }

    /**
     * Download Sales Report as PDF
     */
    public function downloadPdf(Request $request)
    {
        $yearParam = $request->query('year');
        $availableYears = $this->getYears();
        $year = $yearParam ? (int)$yearParam : (!empty($availableYears) ? end($availableYears) : now()->year);

        $rangeType = $request->query('range_type', 'monthly'); // monthly, custom, yearly
        $monthParam = $request->query('month'); // 1-12
        $fromMonth = $request->query('from_month'); // 1-12
        $toMonth = $request->query('to_month'); // 1-12

        // Build query with same filters as index method
        $projects = Project::with(['category', 'quotation.client', 'client', 'statusProject', 'quotation.user']);

        if ($rangeType === 'monthly' && $monthParam) {
            $projects->whereYearFromPn($year)
                    ->whereMonth('po_date', $monthParam);
        } elseif ($rangeType === 'custom' && $fromMonth && $toMonth) {
            $startDate = Carbon::createFromDate($year, $fromMonth, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $toMonth, 1)->endOfMonth();
            $projects->whereBetween('po_date', [$startDate, $endDate]);
        } elseif ($rangeType === 'yearly') {
            $projects->whereYearFromPn($year);
        } else {
            $projects->whereYearFromPn($year);
        }

        $projects = $projects
            ->orderByRaw('CAST(LEFT(CAST(pn_number AS VARCHAR), 2) AS INT) DESC')
            ->orderByRaw('CAST(SUBSTRING(CAST(pn_number AS VARCHAR), 3, LEN(CAST(pn_number AS VARCHAR)) - 2) AS INT) DESC')
            ->get();

        // Prepare filters for PDF
        $filters = [
            'year' => $year,
            'range_type' => $rangeType,
            'month' => $monthParam,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'available_years' => $availableYears,
        ];

        // Generate PDF
        $pdf = new MarketingSalesReportPdf($projects, $filters);
        $pdf->build();

        // Generate filename
        $filename = 'sales_report_';
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        if ($rangeType === 'monthly' && $monthParam) {
            $filename .= $monthNames[(int)$monthParam] . '_' . $year;
        } elseif ($rangeType === 'custom' && $fromMonth && $toMonth) {
            $filename .= $monthNames[(int)$fromMonth] . '_to_' . $monthNames[(int)$toMonth] . '_' . $year;
        } elseif ($rangeType === 'yearly') {
            $filename .= 'year_' . $year;
        } else {
            $filename .= 'year_' . $year;
        }
        $filename .= '.pdf';

        // Return PDF as download
        // return $pdf->Output('D', $filename);
        $pdfContent = $pdf->Output('S'); // RETURN AS STRING (BUFFER)

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function getYears(): array
    {
        // Dari Project pn_number
        $projectYears = Project::selectRaw('LEFT(CONVERT(VARCHAR(20), pn_number), 2) as year_short')
            ->distinct()
            ->pluck('year_short')
            ->map(fn($y) => (int)('20' . $y)) // tambah 20 untuk full year
            ->toArray();

        // Gabungkan dan unique
        return collect($projectYears)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
