<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserDetailsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\UserDetailsImport;
use App\Models\Category;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportDataController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('Category')->get();

        return view('admin.import-data.index', compact('categories'));
    }

    public function downloadTemplate(Request $request)
    {
        return Excel::download(new UserDetailsTemplateExport(), 'user_details_import_template.xlsx');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|exists:categories,Category',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new UserDetailsImport($validated['category']), $request->file('file'));

        return redirect()
            ->route('admin.import-data.index')
            ->with('success', 'Import completed successfully.');
    }

    public function exportRegisteredData(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'category' => 'nullable|string|exists:categories,Category',
        ]);

        $query = UserDetail::query();

        if (!empty($validated['category'])) {
            $query->where('Category', $validated['category']);
        }

        if (!empty($validated['date_from'])) {
            $from = $validated['date_from'];
            $query->where(function ($q) use ($from) {
                $q->whereDate('Data_Received_At', '>=', $from)
                    ->orWhere(function ($q2) use ($from) {
                        $q2->whereNull('Data_Received_At')
                            ->whereDate('created_at', '>=', $from);
                    });
            });
        }

        if (!empty($validated['date_to'])) {
            $to = $validated['date_to'];
            $query->where(function ($q) use ($to) {
                $q->whereDate('Data_Received_At', '<=', $to)
                    ->orWhere(function ($q2) use ($to) {
                        $q2->whereNull('Data_Received_At')
                            ->whereDate('created_at', '<=', $to);
                    });
            });
        }

        $users = $query->orderByDesc('created_at')->get();
        $formatIst = static function ($value): string {
            if (empty($value)) {
                return '';
            }
            try {
                if ($value instanceof \Carbon\CarbonInterface) {
                    return $value->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
                }
                return \Illuminate\Support\Carbon::parse($value)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                return '';
            }
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Registered Users');

        $headers = [
            'S.No',
            'RegID',
            'Category',
            'Name',
            'Designation',
            'Company',
            'Country',
            'State',
            'City',
            'Email',
            'Mobile',
            'Additional 1',
            'Additional 2',
            'Additional 3',
            'Additional 4',
            'Additional 5',
            'Is Lunch Allowed',
            'Data From',
            'Receipt Number',
            'Data Received At (IST)',
            'Badge Printed At (IST)',
            'Created At (IST)',
            'Updated At (IST)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1e40af'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle('A1:W1')->applyFromArray($headerStyle);
        $sheet->getRowDimension('1')->setRowHeight(24);

        $row = 2;
        foreach ($users as $index => $user) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $user->RegID);
            $sheet->setCellValue('C' . $row, $user->Category);
            $sheet->setCellValue('D' . $row, $user->Name);
            $sheet->setCellValue('E' . $row, $user->Designation);
            $sheet->setCellValue('F' . $row, $user->Company);
            $sheet->setCellValue('G' . $row, $user->Country);
            $sheet->setCellValue('H' . $row, $user->State);
            $sheet->setCellValue('I' . $row, $user->City);
            $sheet->setCellValue('J' . $row, $user->Email);
            $sheet->setCellValue('K' . $row, $user->Mobile);
            $sheet->setCellValue('L' . $row, $user->Additional1);
            $sheet->setCellValue('M' . $row, $user->Additional2);
            $sheet->setCellValue('N' . $row, $user->Additional3);
            $sheet->setCellValue('O' . $row, $user->Additional4);
            $sheet->setCellValue('P' . $row, $user->Additional5);
            $sheet->setCellValue('Q' . $row, $user->IsLunchAllowed ? 'Yes' : 'No');
            $sheet->setCellValue('R' . $row, $user->DataFrom);
            $sheet->setCellValue('S' . $row, $user->ReceiptNumber);
            $sheet->setCellValue('T' . $row, $formatIst($user->Data_Received_At));
            $sheet->setCellValue('U' . $row, $formatIst($user->Badge_Printed_At));
            $sheet->setCellValue('V' . $row, $formatIst($user->created_at));
            $sheet->setCellValue('W' . $row, $formatIst($user->updated_at));

            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':W' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f9fafb');
            }

            $row++;
        }

        foreach (range('A', 'W') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $lastRow = max(2, $row - 1);
        $sheet->getStyle('A1:W' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $filename = 'registered_users_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'registered_users_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}

