<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PresenceTableExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $workers;
    protected $presenceData;
    protected $workplace;
    protected $date;

    public function __construct($workers, $presenceData, $workplace, $date)
    {
        $this->workers = $workers;
        $this->presenceData = $presenceData;
        $this->workplace = $workplace;
        $this->date = $date;
    }

    public function collection()
    {
        return collect($this->workers);
    }

    public function headings(): array
    {
        return [
            'Име',
            'Презиме', 
            'Фамилия',
            'ЕГН',
            'Дейност',
            'Часове',
            'Статус'
        ];
    }

    public function map($worker): array
    {
        $presenceRecord = $this->presenceData->get($worker->id);
        
        $status = 'Отсъства';
        if ($presenceRecord) {
            switch ($presenceRecord->status) {
                case 0:
                    $status = 'Чакащ';
                    break;
                case 1:
                    $status = 'Одобрен';
                    break;
                case 2:
                    $status = 'Отхвърлен';
                    break;
                case 3:
                    $status = 'Приключен';
                    break;
            }
        }
        
        return [
            $worker->name ?? '',
            $worker->middle_name ?? '',
            $worker->family_name ?? '',
            $worker->egn ?? '',
            $presenceRecord?->activity?->activity ?? 'Не е зададена',
            $presenceRecord?->hours ?? 0,
            $status
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
