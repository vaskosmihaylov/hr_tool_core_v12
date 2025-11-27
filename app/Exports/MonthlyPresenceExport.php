<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;

class MonthlyPresenceExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $groupedByActivity;
    protected $workplaceData;
    protected $year;
    protected $month;
    protected $daysInMonth;
    protected $rows = [];

    public function __construct($groupedByActivity, $workplaceData, $year, $month, $daysInMonth)
    {
        $this->groupedByActivity = $groupedByActivity;
        $this->workplaceData = $workplaceData;
        $this->year = $year;
        $this->month = $month;
        $this->daysInMonth = $daysInMonth;
        $this->buildRows();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headers = [
            'Длъжност',
            'Заплата',
            'Име',
            'Фамилия',
            'ЕГН',
        ];

        // Add day columns (1-31)
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $headers[] = $day;
        }

        // Add summary columns
        $headers[] = 'Цена';
        $headers[] = 'Общо';

        return $headers;
    }

    protected function buildRows(): void
    {
        foreach ($this->groupedByActivity as $activityData) {
            // Activity header row
            $activityRow = [
                $activityData['activity_name'], // Длъжност (Activity name)
                (float) $activityData['activity_salary'], // Заплата (as number, not formatted string)
                '-', // Име
                '-', // Фамилия
                '-', // ЕГН
            ];

            // Add empty cells for each day
            for ($day = 1; $day <= $this->daysInMonth; $day++) {
                $activityRow[] = '-';
            }

            // Add totals with permissible amounts
            $usedBudget = $activityData['group_totals']['used_budget'];
            $maxBudget = $activityData['group_totals']['max_budget'];
            $usedHours = $activityData['group_totals']['used_hours'];
            $maxHours = $activityData['group_totals']['max_hours'];

            // Store as "used / max" string for display
            $activityRow[] = round($usedBudget, 0) . ' / ' . round($maxBudget, 0);
            $activityRow[] = round($usedHours, 0) . ' / ' . round($maxHours, 0);

            $this->rows[] = $activityRow;

            // Worker rows for this activity
            foreach ($activityData['workers'] as $workerData) {
                $worker = $workerData['worker'];
                $workerRow = [
                    '-', // Длъжност (empty for workers)
                    '-', // Заплата (empty for workers)
                    $worker->name ?? '', // Име
                    $worker->family_name ?? '', // Фамилия
                    $worker->egn ?? '', // ЕГН
                ];

                // Add daily hours as numbers (not formatted strings)
                for ($day = 1; $day <= $this->daysInMonth; $day++) {
                    $hours = $workerData['daily_records'][$day] ?? 0;
                    $workerRow[] = $hours > 0 ? (float) $hours : ''; // Store as float or empty string
                }

                // Add worker totals as numbers
                $workerRow[] = round($workerData['calculated_price'], 0);
                $workerRow[] = round($workerData['total_hours'], 0);

                $this->rows[] = $workerRow;
            }
        }
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->rows) + 1; // +1 for header row
        $lastColumn = $this->getLastColumnLetter();

        // Apply borders to all cells
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Header row styling
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => 'FFFFFFFF'], // White text
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4B5563'], // Gray-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Apply number format to numeric columns (days + price + total)
        $firstDayColumn = 'F'; // Column F is day 1 (after A=Длъжност, B=Заплата, C=Име, D=Фамилия, E=ЕГН)
        $priceColumn = chr(ord($firstDayColumn) + $this->daysInMonth); // After all days
        $totalColumn = chr(ord($priceColumn) + 1); // After price

        // Format day columns, price, and total as numbers
        $sheet->getStyle("{$firstDayColumn}2:{$totalColumn}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER);

        // Style activity header rows (they have activity name in column A)
        $currentRow = 2; // Start after header
        foreach ($this->groupedByActivity as $activityData) {
            // Activity header row
            $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD1FAE5'], // Pale green
                ],
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

            $currentRow += count($activityData['workers']) + 1;
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Длъжност
            'B' => 10, // Заплата
            'C' => 12, // Име
            'D' => 12, // Фамилия
            'E' => 15, // ЕГН
            // Days columns get default width
        ];
    }

    protected function getLastColumnLetter(): string
    {
        // Calculate last column: 5 fixed columns + days + 2 summary columns
        $columnCount = 5 + $this->daysInMonth + 2;

        if ($columnCount <= 26) {
            return chr(64 + $columnCount);
        } else {
            $firstLetter = chr(64 + floor(($columnCount - 1) / 26));
            $secondLetter = chr(64 + (($columnCount - 1) % 26) + 1);
            return $firstLetter . $secondLetter;
        }
    }
}
