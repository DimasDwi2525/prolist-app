<?php

namespace App\Libraries;

require_once app_path('Libraries/fpdf/fpdf.php');


use FPDF;

class WorkOrderPdf extends FPDF
{
    protected $workOrder;
    protected $data;

    protected $pics;

    protected $isTableContinuation = false;

    public function __construct($workOrder, $data, $pics)
    {
        parent::__construct();
        $this->workOrder = $workOrder;
        $this->data = $data;
        $this->pics = $pics;
        $this->SetAutoPageBreak(true, 10); // Enable automatic page breaks with 10mm margin
    }

    // Header
    function Header()
    {
        $this->SetFont('Arial','B',12);
        $this->Cell(95,15,'WORK ORDER FORM',1,0,'L');
        // Buat Cell kosong dulu (border aja)
        $this->Cell(25, 15, '', 1, 0, 'L');

        // Ambil posisi cell yang baru dibuat
        $x = $this->GetX() - 25; // posisi awal cell (karena GetX() sudah maju setelah Cell)
        $y = $this->GetY();

        // Hitung supaya logo berada di tengah cell
        $cellWidth = 25;
        $cellHeight = 15;
        $imgWidth = 20;
        $imgHeight = 10;

        $imgX = $x + ($cellWidth - $imgWidth) / 2;
        $imgY = $y + ($cellHeight - $imgHeight) / 2;

        // Taruh gambar
        $this->Image(public_path('images/CITASys Logo.jpg'), $imgX, $imgY, $imgWidth, $imgHeight);
        $this->Cell(40,15, $this->workOrder->wo_kode_no,1,0,'L');
        $this->MultiCell(30, 5, "FRM-ENG-03\nRev. 01\n22-04-2013", 1, 'L');
    }

    // Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
    }

    public function build()
    {
        $this->AddPage();
        $this->SetFont('Arial','',10);

        $this->SetFont('Arial','',10);

        // Bagian atas tabel
        $this->Cell(25,6,'Issued Date',1,0);
        $this->Cell(70, 6, $this->workOrder->wo_date ? $this->workOrder->wo_date->format('d-m-Y') : '-', 1, 0);
        $this->Cell(25,6,'Project No',1,0);
        $this->Cell(70,6,$this->workOrder->project->project_number,1,1);

        $this->Cell(25,6,'Client',1,0);
        $clientName = $this->workOrder->project->client->name
        ?? ($this->workOrder->project->quotation->client->name ?? '-');
        $this->Cell(70,6,$clientName,1,0);
        $this->Cell(25,6,'Project Name',1,0);
        $this->Cell(70,6,$this->workOrder->project->project_name,1,1);

        $this->Cell(25,6,'Location',1,0);
        $this->Cell(70,6,$this->workOrder->location,1,0);
        $this->Cell(25,6,'Purpose',1,0);
        $this->Cell(70,6,$this->workOrder->purpose->name,1,1);

        $this->Cell(25,6,'Vehicle No',1,0);
        $this->Cell(25,6,$this->workOrder->vehicle_no,1,0);
        $this->Cell(15,6,'Driver',1,0);
        $this->Cell(30,6,$this->workOrder->driver,1,0);
        $noOfPerson = count($this->pics);
        $this->Cell(25,6,'No of Person',1,0);
        $this->Cell(70,6,$noOfPerson,1,1);

        // Nama & posisi
        $this->SetFont('Arial','B',10);
        $this->Cell(10,5,'No',1,0,'C');
        $this->Cell(43,5,'Name',1,0,'C');
        $this->Cell(42,5,'Position',1,0,'C');
        $this->Cell(10,5,'No',1,0,'C');
        $this->Cell(43,5,'Name',1,0,'C');
        $this->Cell(42,5,'Position',1,1,'C');



        // Isi max 5 baris (10 PIC slot)
        $maxRows = 5;
        for ($i = 0; $i < $maxRows; $i++) {
            // PIC kiri
            $leftIndex = $i;
            $leftNo = $i + 1;
            if (isset($this->pics[$leftIndex])) {
                $pic = $this->pics[$leftIndex];
                $this->Cell(10,6,$leftNo,1,0,'C');
                $this->Cell(43,6,$pic['name'],1,0,'L');
                $this->Cell(42,6,$pic['role'],1,0,'L');
            } else {
                $this->Cell(10,6,'',1,0,'C');
                $this->Cell(43,6,'',1,0,'L');
                $this->Cell(42,6,'',1,0,'L');
            }

            // PIC kanan
            $rightIndex = $i + 5;
            $rightNo = $i + 6;
            if (isset($this->pics[$rightIndex])) {
                $pic = $this->pics[$rightIndex];
                $this->Cell(10,6,$rightNo,1,0,'C');
                $this->Cell(43,6,$pic['name'],1,0,'L');
                $this->Cell(42,6,$pic['role'],1,1,'L');
            } else {
                $this->Cell(10,6,'',1,0,'C');
                $this->Cell(43,6,'',1,0,'L');
                $this->Cell(42,6,'',1,1,'L');
            }
        }

                // for ($i=0; $i<5; $i++) {
        //     $this->Cell(10,6,'',1,0,'C');
        //     $this->Cell(43,6,'',1,0,'C');
        //     $this->Cell(42,6,'',1,0,'C');
        //     $this->Cell(10,6,'',1,0,'C');
        //     $this->Cell(43,6,'',1,0,'C');
        //     $this->Cell(42,6,'',1,1,'C');
        // }

        // Work description & result
        $this->SetFont('Arial','B',10);
        $this->Cell(80,8,'Work Description',1,0,'C');
        $this->Cell(110,8,'Result',1,1,'C');

        $this->SetFont('Arial','',10);

        $rowHeight  = 22;      // tinggi minimal baris
        $colDesc = 80;         // lebar kolom Work Description
        $colResult = 110;      // lebar kolom Result

        $maxRows = max(count($this->data), 4);
        for ($i = 0; $i < $maxRows; $i++) {
            $row = isset($this->data[$i]) ? $this->data[$i] : ['desc' => '', 'result' => ''];

            $x = $this->GetX();
            $y = $this->GetY();

            // Tentukan tinggi isi pakai multicell (tanpa border)
            $this->SetXY($x, $y);
            $this->MultiCell($colDesc, 6, utf8_decode($row['desc']), 0, 'L');
            $descHeight = $this->GetY() - $y;

            $this->SetXY($x + $colDesc, $y);
            $this->MultiCell($colResult, 6, utf8_decode($row['result']), 0, 'L');
            $resultHeight = $this->GetY() - $y;

            // Tinggi maksimal baris
            $lineHeight = max($descHeight, $resultHeight, $rowHeight);

            // Check if adding this row would exceed the page height
            if ($y + $lineHeight > $this->PageBreakTrigger) {
                // Add new page and reprint headers
                $this->AddPage();
                $this->SetFont('Arial','B',10);
                $this->Cell(80,8,'Work Description',1,0,'C');
                $this->Cell(110,8,'Result',1,1,'C');
                $this->SetFont('Arial','',10);
                // Reset y to after header
                $y = $this->GetY();
            }

            // Gambar border luar saja (tanpa garis internal MultiCell)
            $this->Rect($x, $y, $colDesc, $lineHeight);
            $this->Rect($x + $colDesc, $y, $colResult, $lineHeight);

            // Isi teks di dalam border
            $this->SetXY($x, $y);
            $this->MultiCell($colDesc, 6, utf8_decode($row['desc']), 0, 'L');
            $this->SetXY($x + $colDesc, $y);
            $this->MultiCell($colResult, 6, utf8_decode($row['result']), 0, 'L');

            // Pindah ke bawah
            $this->SetXY($x, $y + $lineHeight);
        }

        $this->Cell(35,5,'Start Work Time',1,0,'C');
        $this->Cell(65,5,$this->workOrder->start_work_time,1,0,'C');
        $this->Cell(20,10,'Continue on',1,0,'C');
        $this->Cell(10,5,'Date',1,0,'C');
        $this->Cell(60,5,$this->workOrder->continue_date ? $this->workOrder->continue_date->format('d-m-Y') : '-',1,1,'C');

        $this->Cell(35,5,'Stop Work Time',1,0,'C');
        $this->Cell(65,5,$this->workOrder->stop_work_time,1,0,'C');
        $this->Cell(20,5,'',0,0,'C');
        $this->Cell(10,5,'Time',1,0,'C');
        $this->Cell(60,5,$this->workOrder->continue_time,1,1,'C');

        $this->Cell(0, 15, "Client Note:", 1, 'L');


        // Signature
        $this->Cell(49,5,'Requested Digital by',1,0,'C');
        $this->Cell(47,5,'Approved Digital by',1,0,'C');
        $this->Cell(47,5,'Accepted Digital by',1,0,'C');
        $this->Cell(47,5,'Client',1,1,'C');

        // Buat kotak kosong tanda tangan
        $boxHeight = 20;
        $this->Cell(49,$boxHeight,'',1,0,'C');
        $this->Cell(47,$boxHeight,'',1,0,'C');
        $this->Cell(47,$boxHeight,'',1,0,'C');
        $this->Cell(47,$boxHeight,'',1,1,'C');

        // Posisi awal X
        $startX = $this->GetX();
        $startY = $this->GetY() - $boxHeight; // balik ke atas kotak

        // Geser posisi manual & cetak nama di bawah (dengan offset)
        $this->SetXY($startX, $startY + $boxHeight - 5);
        $this->Cell(49,5, optional($this->workOrder->creator)->name ?? '',0,0,'C');

        $this->SetXY($startX+49, $startY + $boxHeight - 5);
        $this->Cell(47,5, optional($this->workOrder->approver)->name ?? '',0,0,'C');

        $this->SetXY($startX+49+47, $startY + $boxHeight - 5);
        $this->Cell(47,5, optional($this->workOrder->acceptor)->name ?? '',0,0,'C');

        $this->SetXY($startX+49+47+47, $startY + $boxHeight - 5);
        $this->Cell(47,5,'',0,0,'C');
        $this->Ln();


        $this->Cell(10,5,'Dept',1,0,'C');
        $this->Cell(39,5,'',1,0,'C');
        $this->Cell(47,5,'',1,0,'C');
        $this->Cell(47,5,'',1,0,'C');
        $this->Cell(47,5,'',1,1,'C');

        $this->SetFont('Arial','',12);
        $this->Cell(55,5,'OVERNIGHT WORK / JOB',1,0,'L');
        $this->Cell(135,5,'[ ] Yes   [ ] No',1,1,'L');

        $this->Cell(30,10,'Scheduled',1,0,'C');
        $this->Cell(23,5,'Start Date ',1,0,'C');
        $this->Cell(43,5,$this->workOrder->scheduled_start_working_date ? $this->workOrder->scheduled_start_working_date->format('d-m-Y') : '-',1,0,'C');
        $this->Cell(30,10,'Actual',1,0,'C');
        $this->Cell(23,5,'Start Date ',1,0,'C');
        $this->Cell(41,5,$this->workOrder->actual_start_working_date ? $this->workOrder->actual_start_working_date->format('d-m-Y') : '-',1,1,'C');


        $this->Cell(30,10,'',0,0,'C');
        $this->Cell(23,5,'End Date ',1,0,'C');
        $this->Cell(43,5,$this->workOrder->scheduled_end_working_date ? $this->workOrder->scheduled_end_working_date->format('d-m-Y') : '-',1,0,'C');
        $this->Cell(30,10,'',0,0,'C');
        $this->Cell(23,5,'End Date ',1,0,'C');
        $this->Cell(41,5,$this->workOrder->actual_end_working_date ? $this->workOrder->actual_end_working_date->format('d-m-Y') : '-',1,1,'C');

        $this->Cell(40,5,'Accommodation',1,0,'C');
        $this->Cell(150,5,'',1,1,'C');

        // simpan posisi awal
        $x = $this->GetX();
        $y = $this->GetY();

        // gambar kotak border saja
        $this->Cell(140,25,'',1,0);

        // isi teks di pojok kiri atas (tanpa border)
        $this->SetXY($x+2, $y+2); // kasih padding 2 biar tidak mepet border
        $this->MultiCell(136,5,'Tools / Material Required',0,'L');

        // pindah kursor ke kanan setelah cell
        $this->SetXY($x+140, $y);

        $this->SetFont('Arial','',9);

        $x = $this->GetX();
        $y = $this->GetY();

        // Lebar 50, tinggi baris dibuat lebih rapat, misalnya 4 (default 5)
        $this->MultiCell(50,3.5,
        "Position Description:
        PM = Project Manager
        SM = Site Manager
        SP = Site/Project Supervisor
        EN = Engineer/Marketing
        AD = Admin
        TE = Technician",1,'L');

        $this->SetXY($x+50, $y);

    }
}
