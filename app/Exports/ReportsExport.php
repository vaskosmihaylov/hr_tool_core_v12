<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportsExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithEvents
{
    protected $workerRecords;
    protected $arraySum;
    protected $bonusData;
    protected $penaltyData;
    protected $vacationData;
    protected $summary;
    protected $month;
    protected $year;

    public function __construct(
        $workerRecords,
        $arraySum,
        $bonusData,
        $penaltyData,
        $vacationData,
        $summary,
        $month,
        $year
    ) {
        $this->workerRecords = $workerRecords;
        $this->arraySum = $arraySum;
        $this->bonusData = $bonusData;
        $this->penaltyData = $penaltyData;
        $this->vacationData = $vacationData;
        $this->summary = $summary;
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
            "Име",
            "Презиме",
            "Фамилия",
            "ЕГН",
            "Обект",
            "Клиент",
            "Регион",
            "Изработени часове",
            "Отпуска (дни)",
            "Отпуска (детайли)",
            "Бонус",
            "Наказание",
            "Сума",
            "Сума + бонус - наказание",
        ];
    }

    public function map($record): array
    {
        // Get client and region names
        $client = $record->clId
            ? \viki\Service\Models\Elequent\Client::find($record->clId)
            : null;
        $region = $record->regId
            ? \viki\Service\Models\Elequent\Region::find($record->regId)
            : null;

        // Get salary, bonus, penalty using unique_id
        $salary = $this->arraySum[$record->unique_id] ?? 0;
        $bonus = $this->bonusData[$record->unique_id] ?? 0;
        $penalty = $this->penaltyData[$record->unique_id] ?? 0;
        $totalWithBonus = $salary + $bonus - $penalty;

        // Get vacation data
        $vacationInfo = $this->vacationData[$record->unique_id] ?? [
            "total_days" => 0,
            "details" => [],
        ];
        $vacationDays = $vacationInfo["total_days"];

        // Format vacation details for Excel
        $vacationDetails = "";
        if (!empty($vacationInfo["details"])) {
            $detailsArray = [];
            foreach ($vacationInfo["details"] as $detail) {
                $detailsArray[] =
                    $detail["type"] .
                    ": " .
                    $detail["days"] .
                    "д (" .
                    $detail["start_date"] .
                    " - " .
                    $detail["end_date"] .
                    ")";
            }
            $vacationDetails = implode("; ", $detailsArray);
        }

        return [
            $record->name ?? "",
            $record->middle_name ?? "",
            $record->family_name ?? "",
            $record->egn ?? "",
            $record->workPlaceName ?? "",
            $client->name ?? "",
            $region->name ?? "",
            (int) ($record->total ?? 0),
            (int) $vacationDays,
            $vacationDetails,
            (float) $bonus,
            (float) $penalty,
            (float) $salary,
            (float) $totalWithBonus,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                "font" => [
                    "bold" => true,
                    "size" => 12,
                ],
                "fill" => [
                    "fillType" =>
                        \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    "startColor" => [
                        "argb" => "FFE6E6E6",
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow() + 1;

                $totalSalary = (float) ($this->summary["total_salary"] ?? 0);
                $totalBonus = (float) ($this->summary["total_bonus"] ?? 0);
                $totalPenalty = (float) ($this->summary["total_penalty"] ?? 0);
                $totalWithBonus = $totalSalary + $totalBonus - $totalPenalty;
                $totalHours = (int) ($this->summary["total_hours"] ?? 0);
                $totalVacationDays =
                    (int) ($this->summary["total_vacation_days"] ?? 0);

                $sheet->fromArray(
                    [
                        [
                            "Общо:",
                            "",
                            "",
                            "",
                            "",
                            "",
                            "",
                            $totalHours,
                            $totalVacationDays,
                            "",
                            $totalBonus,
                            $totalPenalty,
                            $totalSalary,
                            $totalWithBonus,
                        ],
                    ],
                    null,
                    "A" . $lastRow
                );

                $sheet
                    ->getStyle("A" . $lastRow . ":N" . $lastRow)
                    ->applyFromArray([
                        "font" => ["bold" => true],
                        "fill" => [
                            "fillType" => Fill::FILL_SOLID,
                            "startColor" => ["argb" => "FFFFF0CC"],
                        ],
                    ]);
            },
        ];
    }
}
