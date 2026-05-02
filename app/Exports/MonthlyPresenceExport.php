<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use viki\Service\Models\Elequent\Vacation;

class MonthlyPresenceExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $groupedByActivity;
    protected $workplaceData;
    protected $year;
    protected $month;
    protected $daysInMonth;
    protected $nonWorkingDaysMap;
    protected $rows = [];
    protected $leaveCells = [];

    public function __construct($groupedByActivity, $workplaceData, $year, $month, $daysInMonth, array $nonWorkingDaysMap = [])
    {
        $this->groupedByActivity = $groupedByActivity;
        $this->workplaceData = $workplaceData;
        $this->year = $year;
        $this->month = $month;
        $this->daysInMonth = $daysInMonth;
        $this->nonWorkingDaysMap = $nonWorkingDaysMap;
        $this->buildRows();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headers = [[
            'Присъствена форма',
        ], [
            $this->workplaceData->name ?? '—',
        ], [
            'Клиент: ' . ($this->workplaceData->client?->name ?? '—'),
            'Регион: ' . ($this->workplaceData->region?->name ?? '—'),
            'Месец: ' . sprintf('%02d-%d', $this->month, $this->year),
        ], [
            'Длъжност',
            'Заплата',
            'Име',
            'Презиме',
            'Фамилия',
        ]];

        // Add day columns (1-31)
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $headers[3][] = $day;
        }

        // Add summary columns
        $headers[3][] = 'Цена';
        $headers[3][] = 'Общо';

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
                '-', // Презиме
                '-', // Фамилия
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
            $activityRow[] = $this->formatDisplayNumber($usedBudget) . ' / ' . $this->formatDisplayNumber($maxBudget);
            $activityRow[] = $this->formatDisplayNumber($usedHours) . ' / ' . $this->formatDisplayNumber($maxHours);

            $this->rows[] = $activityRow;

            // Worker rows for this activity
            foreach ($activityData['workers'] as $workerData) {
                $worker = $workerData['worker'];
                $dataRowIndex = count($this->rows) + 1;
                $workerRow = [
                    '-', // Длъжност (empty for workers)
                    '-', // Заплата (empty for workers)
                    $worker->name ?? '', // Име
                    $worker->middle_name ?? '', // Презиме
                    $worker->family_name ?? '', // Фамилия
                ];

                // Add daily hours as numbers (not formatted strings)
                for ($day = 1; $day <= $this->daysInMonth; $day++) {
                    $hours = $workerData['daily_records'][$day] ?? 0;
                    $leaveInfo = $workerData['daily_leave_info'][$day] ?? null;

                    if ($hours > 0) {
                        $workerRow[] = (float) $hours;
                        continue;
                    }

                    if ($leaveInfo) {
                        $workerRow[] = $leaveInfo['short'] ?? '';
                        $this->leaveCells[] = [
                            'data_row' => $dataRowIndex,
                            'day' => $day,
                            'type' => (int) ($leaveInfo['type'] ?? 0),
                        ];
                        continue;
                    }

                    $workerRow[] = '';
                }

                // Add worker totals as numbers
                $workerRow[] = round($workerData['calculated_price'], 2);
                $workerRow[] = round($workerData['total_hours'], 2);

                $this->rows[] = $workerRow;
            }
        }
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 4;
        $dataStartRow = 5;
        $lastRow = count($this->rows) + $headerRow;
        $lastColumn = $this->getLastColumnLetter();
        $firstDayColumnIndex = 6;
        $priceColumnIndex = $firstDayColumnIndex + $this->daysInMonth;
        $totalColumnIndex = $priceColumnIndex + 1;
        $firstDayColumn = Coordinate::stringFromColumnIndex($firstDayColumnIndex);
        $priceColumn = Coordinate::stringFromColumnIndex($priceColumnIndex);
        $totalColumn = Coordinate::stringFromColumnIndex($totalColumnIndex);

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");

        // Apply borders to all cells
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['argb' => 'FF111827'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => 'FF111827'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF3F4F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("A3:C3")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['argb' => 'FF4B5563'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header row styling
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => 'FFFFFFFF'], // White text
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF000000'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Format day columns, price, and total as numbers
        $sheet->getStyle("{$firstDayColumn}{$dataStartRow}:{$totalColumn}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        // Style activity header rows (they have activity name in column A)
        $currentRow = $dataStartRow;
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

        foreach (array_keys($this->nonWorkingDaysMap) as $day) {
            $column = Coordinate::stringFromColumnIndex($firstDayColumnIndex + ((int) $day - 1));

            $sheet->getStyle("{$column}{$headerRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF9CA3AF'],
                ],
            ]);

            if ($lastRow >= $dataStartRow) {
                $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$lastRow}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFECACA'],
                    ],
                ]);
            }
        }

        foreach ($this->leaveCells as $leaveCell) {
            $row = $dataStartRow + ((int) $leaveCell['data_row']) - 1;
            $column = Coordinate::stringFromColumnIndex(
                $firstDayColumnIndex + ((int) $leaveCell['day'] - 1)
            );

            $sheet->getStyle("{$column}{$row}")->applyFromArray(
                $this->getLeaveCellStyle((int) $leaveCell['type'])
            );
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Длъжност
            'B' => 10, // Заплата
            'C' => 12, // Име
            'D' => 12, // Презиме
            'E' => 12, // Фамилия
            // Days columns get default width
        ];
    }

    protected function getLastColumnLetter(): string
    {
        // Calculate last column: 5 fixed columns + days + 2 summary columns
        $columnCount = 5 + $this->daysInMonth + 2;

        return Coordinate::stringFromColumnIndex($columnCount);
    }

    protected function formatDisplayNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    protected function getLeaveCellStyle(int $type): array
    {
        $styles = [
            Vacation::PAYD_VACATION => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDCFCE7'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF166534'],
                ],
            ],
            Vacation::NOT_PAYD_VACATION => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDBEAFE'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF1D4ED8'],
                ],
            ],
            Vacation::HOSPITAL_SHEET => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFEE2E2'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF991B1B'],
                ],
            ],
        ];

        return array_merge(
            $styles[$type] ?? [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF3F4F6'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF374151'],
                ],
            ],
            [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]
        );
    }
}
