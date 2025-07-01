<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VikiReportsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $workerRecords;
    protected $arraySum;
    protected $month;
    protected $year;

    public function __construct($workerRecords, $arraySum, $month, $year)
    {
        $this->workerRecords = $workerRecords;
        $this->arraySum = $arraySum;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        return collect($this->workerRecords);
    }

    public function headings(): array
    {
        return [
            'Име',
            'Презиме', 
            'Фамилия',
            'ЕГН',
            'Обект',
            'Клиент',
            'Регион',
            'Изработени часове',
            'Бонус',
            'Наказание',
            'Сума',
            'Сума + бонус'
        ];
    }

    public function map($record): array
    {
        // Get client and region names
        $client = $record->clId ? \viki\Service\Models\Elequent\Client::find($record->clId) : null;
        $region = $record->regId ? \viki\Service\Models\Elequent\Region::find($record->regId) : null;
        
        $salary = $this->arraySum[$record->ID] ?? 0;
        
        return [
            $record->name ?? '',
            $record->middle_name ?? '',
            $record->family_name ?? '',
            $record->egn ?? '',
            $record->workPlaceName ?? '',
            $client->name ?? '',
            $region->name ?? '',
            $record->total ?? 0,
            0, // Bonus
            0, // Penalty
            number_format($salary, 2),
            number_format($salary, 2), // Total with bonus
        ];
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
