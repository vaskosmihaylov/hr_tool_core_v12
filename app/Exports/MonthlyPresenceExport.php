<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class MonthlyPresenceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $monthlyData;
    protected $workplace;
    protected $year;
    protected $month;
    protected $daysInMonth;

    public function __construct($monthlyData, $workplace, $year, $month)
    {
        $this->monthlyData = $monthlyData;
        $this->workplace = $workplace;
        $this->year = $year;
        $this->month = $month;
        $this->daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
    }

    public function collection()
    {
        return collect($this->monthlyData);
    }

    public function headings(): array
    {
        $headers = [
            'Име',
            'Презиме', 
            'Фамилия',
            'ЕГН',
            'Общо часове',
            'Работни дни',
            'Средно ч./ден'
        ];

        // Add daily columns
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $headers[] = "Ден {$day}";
        }

        return $headers;
    }

    public function map($data): array
    {
        $worker = $data['worker'];
        $records = $data['records'];

        $row = [
            $worker->name ?? '',
            $worker->middle_name ?? '',
            $worker->family_name ?? '',
            $worker->egn ?? '',
            number_format($data['total_hours'], 1),
            $data['working_days'],
            number_format($data['average_hours'], 1)
        ];

        // Add daily hours
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $dayRecord = $records->get($day);
            $row[] = $dayRecord ? $dayRecord->hours : '';
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFE6E6E6',
                    ],
                ],
            ],
        ];
    }
}
