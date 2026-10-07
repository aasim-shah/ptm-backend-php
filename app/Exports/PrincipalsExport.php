<?php

namespace App\Exports;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class PrincipalsExport implements FromView,ShouldAutoSize,WithEvents,WithColumnFormatting
{

    use Exportable;

    private $principals;
    private $createdDate;

    public function __construct($principals)
    {
        $this->principals = $principals;
        $this->createdDate = Carbon::now()->toDateString();
    }

    /**
     * @return View
     */
    public function view(): View
    {
        return view('excel.principals')
            ->with('principals', $this->principals)
            ->with('createdDate',$this->createdDate);
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
//                $cellRange = 'A1:B1:C1:D1:E1:F1:G1:H1:I1';
//                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(12)->setBold(true);
            },
        ];
    }
    public function columnFormats(): array{
        return [
            'A' => '0',
            'F' => '0'
        ];
    }
}