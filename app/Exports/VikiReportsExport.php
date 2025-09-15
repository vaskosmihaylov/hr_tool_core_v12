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
    protected $bonusData;
    protected $penaltyData;
    protected $vacationData;
    protected $month;
    protected $year;

    public function __construct($workerRecords, $arraySum, $bonusData, $penaltyData, $vacationData, $month, $year)
    {
        $this->workerRecords = $workerRecords;
        $this->arraySum = $arraySum;
        $this->bonusData = $bonusData;
        $this->penaltyData = $penaltyData;
        $this->vacationData = $vacationData;
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
            'Отпуска (дни)',
            'Отпуска (детайли)',
            'Бонус',
            'Наказание',
            'Сума',
            'Сума + бонус - наказание'
        ];
    }

    public function map($record): array
    {
        // Get client and region names
        $client = $record->clId ? \viki\Service\Models\Elequent\Client::find($record->clId) : null;
        $region = $record->regId ? \viki\Service\Models\Elequent\Region::find($record->regId) : null;
        
        // Get salary, bonus, penalty using unique_id
        $salary = $this->arraySum[$record->unique_id] ?? 0;
        $bonus = $this->bonusData[$record->unique_id] ?? 0;
        $penalty = $this->penaltyData[$record->unique_id] ?? 0;
        $totalWithBonus = $salary + $bonus - $penalty;
        
        // Get vacation data
        $vacationInfo = $this->vacationData[$record->unique_id] ?? ['total_days' => 0, 'details' => []];
        $vacationDays = $vacationInfo['total_days'];
        
        // Format vacation details for Excel
        $vacationDetails = '';
        if (!empty($vacationInfo['details'])) {
            $detailsArray = [];
            foreach ($vacationInfo['details'] as $detail) {
                $detailsArray[] = $detail['type'] . ': ' . $detail['days'] . 'д (' . $detail['start_date'] . ' - ' . $detail['end_date'] . ')';
            }
            $vacationDetails = implode('; ', $detailsArray);
        }
        
        return [
            $record->name ?? '',
            $record->middle_name ?? '',
            $record->family_name ?? '',
            $record->egn ?? '',
            $record->workPlaceName ?? '',
            $client->name ?? '',
            $region->name ?? '',
            $record->total ?? 0,
            $vacationDays,
            $vacationDetails,
            number_format($bonus, 2),
            number_format($penalty, 2),
            number_format($salary, 2),
            number_format($totalWithBonus, 2),
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
