<?php

// app/Exports/TableExport.php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TableExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(
        protected array $headings,
        protected array $rows
    ) {}

    public function array(): array { return $this->rows; }
    public function headings(): array { return $this->headings; }

    public function styles(Worksheet $sheet)
    {
        $styles = [];
        
        foreach ($this->rows as $index => $row) {
            // Determine Excel row index (1-based)
            // If headings provided, data starts at 2. If empty, maybe 1? 
            // We'll stick to logic: if headings empty, +1. Else +2.
            $excelRow = empty($this->headings) ? $index + 1 : $index + 2;

            if (empty($row)) continue;

            $firstCell = $row[0] ?? '';
            
            if (is_string($firstCell)) {
                
                // 1. Check for Main Header "CONCEPTO"
                if ($firstCell === 'CONCEPTO') {
                     $styles[$excelRow] = [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '334155']], // Slate-700
                    ];
                }

                // 2. Check for Totals or Uppercase headers/sections
                elseif (preg_match('/^(TOTAL|SALDO|VENTA|IVA|CONSIGNACIONES|CODIGOS|%)/', $firstCell) || $firstCell === 'N° MÁQUINAS') {
                    $styles[$excelRow] = ['font' => ['bold' => true]];
                    
                    // Colors for specific sections
                    if (str_contains($firstCell, 'TOTAL') || str_contains($firstCell, 'SALDO')) {
                        $styles[$excelRow]['fill'] = ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']]; // Light Gray
                    }
                    if (str_contains($firstCell, 'VENTA + IVA')) {
                         $styles[$excelRow] = [
                             'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                             'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '475569']]
                         ];
                    }
                     if (str_contains($firstCell, 'SALDO')) {
                         $styles[$excelRow] = [
                             'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                             'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']] // Indigo
                         ];
                    }
                }
            }
        }

        return $styles;
    }
}
