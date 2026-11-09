<?php

namespace App\Services\DataExchange;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as WriterOptions;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class XlsxDataFile
{
    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $path = tempnam(sys_get_temp_dir(), 'invman-xlsx-');
            $options = new WriterOptions;
            // Shared strings also work in Numbers and the iOS previewer.
            $options->SHOULD_USE_INLINE_STRINGS = false;
            $options->DEFAULT_COLUMN_WIDTH = 24;
            $options->DEFAULT_ROW_STYLE = (new Style)->setShouldWrapText(true);
            $writer = new Writer($options);
            try {
                $writer->openToFile($path);
                $sheet = $writer->getCurrentSheet();
                $sheet->setName('Data');
                $sheet->setSheetView((new SheetView)->setFreezeRow(2));
                $headerStyle = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('4338CA')->setShouldWrapText(true);
                $writer->addRow(Row::fromValues($columns, $headerStyle));
                foreach ($rows as $values) {
                    $writer->addRow(new Row(array_map($this->cell(...), $values)));
                }
                $writer->close();
                readfile($path);
            } finally {
                $writer->close();
                @unlink($path);
            }
        }, $filename, ['Content-Type' => self::MIME_TYPE, 'Cache-Control' => 'no-store, private']);
    }

    private function cell(mixed $value): Cell
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (is_bool($value)) {
            $value = (int) $value;
        }

        // OpenSpout's reader classifies any shared string starting with "=" as a
        // FormulaCell, even when the XLSX writer stored it as plain text. Prefix
        // one apostrophe (including already-prefixed values) to make exports
        // safe and preserve exact values when imported again.
        if (is_string($value) && preg_match("/^'*=/" , $value) === 1) {
            $value = "'".$value;
        }

        return is_string($value) ? new StringCell($value, null) : Cell::fromValue($value);
    }

    /** Return spreadsheet row numbers so validation points to the user's Excel row. */
    public function readRows(string $path, array $allowed): array
    {
        $this->validateWorkbook($path);
        $options = new ReaderOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        try {
            $reader->open($path);
            $rows = [];
            $header = null;
            $sheetCount = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                if (++$sheetCount > 1) {
                    throw ValidationException::withMessages(['file' => 'Gunakan satu lembar kerja saja, sesuai template XLSX.']);
                }
                foreach ($sheet->getRowIterator() as $line => $excelRow) {
                    if ($line > 10000) {
                        throw ValidationException::withMessages(['file' => 'Hapus baris kosong berlebih. Letakkan data dalam 10.000 baris pertama.']);
                    }
                    $values = [];
                    foreach ($excelRow->getCells() as $index => $cell) {
                        if ($cell instanceof FormulaCell || $cell instanceof ErrorCell) {
                            throw ValidationException::withMessages(['file' => 'Baris '.$line.': gunakan nilai langsung, bukan formula atau galat Excel. Salin lalu tempel sebagai nilai.']);
                        }
                        $values[$index] = $this->importValue($cell->getValue(), $header[$index] ?? '');
                    }
                    if ($line === 1) {
                        while ($values && end($values) === null) {
                            array_pop($values);
                        }
                        $header = $values;
                        if (! $header || in_array(null, $header, true) || count(array_unique($header)) !== count($header) || array_diff($header, $allowed)) {
                            throw ValidationException::withMessages(['file' => 'Header kosong, duplikat, atau tidak dikenal. Unduh template XLSX terbaru dan pertahankan nama kolomnya.']);
                        }
                        continue;
                    }
                    if (! array_filter($values, fn ($value) => $value !== null)) {
                        continue;
                    }
                    if (array_filter(array_slice($values, count($header)), fn ($value) => $value !== null)) {
                        throw ValidationException::withMessages(['file' => 'Baris '.$line.': ada data di luar kolom header.']);
                    }
                    if (count($rows) >= 1000) {
                        throw ValidationException::withMessages(['file' => 'Maksimal 1.000 baris per impor. Pecah berkas menjadi beberapa bagian.']);
                    }
                    $rows[$line] = array_combine($header, array_slice(array_pad($values, count($header), null), 0, count($header)));
                }
            }
            if (! $rows) {
                throw ValidationException::withMessages(['file' => 'Berkas belum memiliki baris data. Isi template terlebih dahulu.']);
            }

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak dapat dibaca. Simpan ulang sebagai Excel Workbook (.xlsx) dan gunakan template terbaru.']);
        } finally {
            $reader->close();
        }
    }

    private function importValue(mixed $value, string $column): ?string
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(match (true) {
                in_array($column, ['valid_from', 'valid_until', 'date', 'stnk_date', 'kir_date'], true) => 'Y-m-d',
                in_array($column, ['start_time', 'end_time'], true) && $value->format('Y') < 1901 => 'H:i:s',
                default => 'Y-m-d H:i:s',
            });
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        $value = trim((string) $value);
        // Undo exactly one quote used to protect literal formula-looking text
        // in exported XLSX. Real Excel FormulaCell values are rejected above.
        if (preg_match("/^'+=/" , $value) === 1) {
            $value = substr($value, 1);
        }

        return $value === '' ? null : $value;
    }

    private function validateWorkbook(string $path): void
    {
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Berkas XLSX maksimal 5 MB.']);
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Gunakan berkas Excel Workbook (.xlsx), sesuai template.']);
        }
        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
            }
            if ($size > 50 * 1024 * 1024 || $zip->numFiles > 1000) {
                throw ValidationException::withMessages(['file' => 'Isi workbook terlalu besar. Pecah data menjadi beberapa berkas XLSX.']);
            }
            if ($zip->locateName('xl/workbook.xml') === false || ! str_contains($zip->getFromName('[Content_Types].xml') ?: '', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml')) {
                throw ValidationException::withMessages(['file' => 'Gunakan berkas Excel Workbook (.xlsx), sesuai template.']);
            }
        } finally {
            $zip->close();
        }
    }
}
