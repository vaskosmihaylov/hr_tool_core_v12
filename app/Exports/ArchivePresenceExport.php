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
use Illuminate\Support\Carbon;
use viki\Service\Models\Elequent\Archive;

class ArchivePresenceExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected Archive $archive;
    protected array $archiveData;
    protected int $year;
    protected int $month;
    protected int $daysInMonth;
    protected array $rows = [];

    public function __construct(Archive $archive)
    {
        $this->archive = $archive->loadMissing('workplace');
        $date = Carbon::parse($this->archive->date);
        $this->year = $date->year;
        $this->month = $date->month;
        $this->daysInMonth = $date->daysInMonth;
        $this->archiveData = json_decode($this->archive->json_data, true) ?? [];
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
        foreach ($this->archiveData as $activityId => $activity) {
            $workers = $activity['workPlaceActivityWorkers'] ?? [];

            if (empty($workers)) {
                continue;
            }

            // Pre-calculate activity totals
            $activityTotalPrice = 0;
            $activityTotalHours = 0;

            foreach ($workers as $worker) {
                $workerRecords = $worker['worker_records'] ?? [];
                $workerTotal = 0;

                foreach ($workerRecords as $record) {
                    if (isset($record['date'], $record['hours'])) {
                        $workerTotal += $record['hours'];
                    }
                }

                $workerPrice = $workerTotal * ($activity['workPlaceActivityHourPrice'] ?? 0);
                $activityTotalPrice += $workerPrice;
                $activityTotalHours += $workerTotal;
            }

            // Activity header row
            $activityRow = [
                $activity['workPlaceActivityName'] ?? 'Активност', // Длъжност
                (float) ($activity['workPlaceActivitySalary'] ?? 0), // Заплата (as number)
                '-', // Име
                '-', // Фамилия
            ];

            // Add empty cells for each day
            for ($day = 1; $day <= $this->daysInMonth; $day++) {
                $activityRow[] = '-';
            }

            // Add totals with permissible amounts
            $maxBudget = $activity['workPlaceActivityBudget'] ?? 0;
            $maxHours = $activity['workPlaceActivityMaxWorkingHours'] ?? 0;

            // Format as "used / max" but store as string for display
            $activityRow[] = round($activityTotalPrice, 0) . ' / ' . round($maxBudget, 0);
            $activityRow[] = round($activityTotalHours, 0) . ' / ' . round($maxHours, 0);

            $this->rows[] = $activityRow;

            // Worker rows for this activity
            foreach ($workers as $worker) {
                $workerRecords = $worker['worker_records'] ?? [];

                // Build daily records indexed by day
                $recordsByDay = [];
                foreach ($workerRecords as $record) {
                    if (isset($record['date'])) {
                        $dayIndex = (int) Carbon::parse($record['date'])->format('j');
                        $recordsByDay[$dayIndex] = (float) ($record['hours'] ?? 0);
                    }
                }

                $workerTotal = array_sum($recordsByDay);
                $workerPrice = $workerTotal * ($activity['workPlaceActivityHourPrice'] ?? 0);

                $workerRow = [
                    '-', // Длъжност (empty for workers)
                    '-', // Заплата (empty for workers)
                    $worker['name'] ?? '', // Име
                    $worker['family_name'] ?? '', // Фамилия
                ];

                // Add daily hours as numbers (not formatted strings)
                for ($day = 1; $day <= $this->daysInMonth; $day++) {
                    $hours = $recordsByDay[$day] ?? 0;
                    $workerRow[] = $hours > 0 ? (float) $hours : ''; // Store as float or empty string
                }

                // Add worker totals as numbers
                $workerRow[] = round($workerPrice, 0);
                $workerRow[] = round($workerTotal, 0);

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
        $firstDayColumn = 'E'; // Column E is day 1
        $priceColumn = chr(ord($firstDayColumn) + $this->daysInMonth); // After all days
        $totalColumn = chr(ord($priceColumn) + 1); // After price

        // Format day columns, price, and total as numbers
        $sheet->getStyle("{$firstDayColumn}2:{$totalColumn}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER);

        // Style activity header rows (pale green background)
        $currentRow = 2; // Start after header
        $activityCount = 0;

        foreach ($this->archiveData as $activity) {
            $workers = $activity['workPlaceActivityWorkers'] ?? [];
            if (empty($workers)) {
                continue;
            }

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

            $currentRow += count($workers) + 1;
            $activityCount++;
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
            // Days columns get default width
        ];
    }

    protected function getLastColumnLetter(): string
    {
        // Calculate last column: 4 fixed columns + days + 2 summary columns
        $columnCount = 4 + $this->daysInMonth + 2;

        if ($columnCount <= 26) {
            return chr(64 + $columnCount);
        } else {
            $firstLetter = chr(64 + floor(($columnCount - 1) / 26));
            $secondLetter = chr(64 + (($columnCount - 1) % 26) + 1);
            return $firstLetter . $secondLetter;
        }
    }
}
