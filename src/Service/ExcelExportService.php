<?php

namespace App\Service;

use App\Entity\HourEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportService
{
    /**
     * @param HourEntry[] $entries
     */
    public function exportHourEntries(array $entries): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Entêtes
        $headers = ['Développeur', 'Date', 'Début', 'Fin', 'Durée (h)', 'Activité', 'Projet', 'Commentaire'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $rowIdx = 2;
        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $hoursDecimal = $diff->h + ($diff->i / 60);

            $sheet->setCellValue('A' . $rowIdx, $entry->getUser()->getLastname() . ' ' . $entry->getUser()->getFirstname());
            $sheet->setCellValue('B' . $rowIdx, $entry->getStartDate()->format('d/m/Y'));
            $sheet->setCellValue('C' . $rowIdx, $entry->getStartDate()->format('H:i'));
            $sheet->setCellValue('D' . $rowIdx, $entry->getEndDate()->format('H:i'));
            $sheet->setCellValue('E' . $rowIdx, round($hoursDecimal, 2));
            $sheet->setCellValue('F' . $rowIdx, $entry->getActivity()?->getLabel() ?? '-');
            $sheet->setCellValue('G' . $rowIdx, $entry->getProject()?->getName() ?? '-');
            $sheet->setCellValue('H' . $rowIdx, $entry->getCommentary());
            $rowIdx++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);

        return $tempFile;
    }
}