<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class ParticipantImportService
{
    private const MAX_ROWS = 5000;
    private const MAX_XML_BYTES = 25_000_000;

    /**
     * Import valid rows and report invalid or duplicate rows without discarding the valid ones.
     *
     * @return array{added:int,rejected:int,errors:array<int,string>}
     */
    public function import(Project $project, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('Formato não suportado. Envie um arquivo CSV ou XLSX.'),
        };

        if ($rows === []) {
            throw new RuntimeException('A planilha não contém linhas para importar.');
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_shift($rows));
        $nameIndex = $this->findHeaderIndex($headers, ['nome', 'name']);
        $emailIndex = $this->findHeaderIndex($headers, ['email', 'e-mail']);

        if ($nameIndex === null || $emailIndex === null) {
            throw new RuntimeException('A primeira linha deve conter as colunas "nome" e "email".');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('A planilha excede o limite de '.self::MAX_ROWS.' linhas por importação.');
        }

        $existingEmails = [];
        foreach ($project->participants()->pluck('email') as $email) {
            $existingEmails[mb_strtolower(trim((string) $email))] = true;
        }

        $seenEmails = [];
        $records = [];
        $errors = [];
        $rejectedCount = 0;

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            $name = trim((string) ($row[$nameIndex] ?? ''));
            $email = trim((string) ($row[$emailIndex] ?? ''));

            if ($name === '' && $email === '') {
                continue;
            }

            $validator = Validator::make(
                ['name' => $name, 'email' => $email],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'string', 'email', 'max:255'],
                ],
                [
                    'name.required' => 'nome está vazio',
                    'name.max' => 'nome ultrapassa 255 caracteres',
                    'email.required' => 'e-mail está vazio',
                    'email.email' => 'e-mail inválido',
                    'email.max' => 'e-mail ultrapassa 255 caracteres',
                ]
            );

            if ($validator->fails()) {
                $rejectedCount++;
                $this->addError($errors, $line, $validator->errors()->first());
                continue;
            }

            $emailKey = mb_strtolower($email);
            if (isset($existingEmails[$emailKey]) || isset($seenEmails[$emailKey])) {
                $rejectedCount++;
                $this->addError($errors, $line, 'e-mail duplicado neste projeto');
                continue;
            }

            $seenEmails[$emailKey] = true;
            $records[] = [
                'uuid' => (string) Str::uuid(),
                'project_id' => $project->id,
                'name' => $name,
                'email' => $email,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            Participant::query()->insert($chunk);
        }

        return [
            'added' => count($records),
            'rejected' => $rejectedCount,
            'errors' => $errors,
        ];
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string|false $path): array
    {
        if (!$path || !($handle = fopen($path, 'rb'))) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return [];
            }

            $delimiter = ',';
            $bestColumns = 0;
            foreach ([',', ';', "\t"] as $candidate) {
                $columns = count(str_getcsv($firstLine, $candidate));
                if ($columns > $bestColumns) {
                    $delimiter = $candidate;
                    $bestColumns = $columns;
                }
            }

            rewind($handle);
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $rows[] = array_map(fn ($value) => $this->toUtf8((string) ($value ?? '')), $row);
                if (count($rows) > self::MAX_ROWS + 1) {
                    throw new RuntimeException('A planilha excede o limite de '.self::MAX_ROWS.' linhas por importação.');
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int, array<int, string>> */
    private function readXlsx(string|false $path): array
    {
        if (!$path) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('O arquivo XLSX não é válido ou está corrompido.');
        }

        try {
            $sharedStrings = [];
            $sharedXml = $this->readZipXml($zip, 'xl/sharedStrings.xml', required: false);
            if ($sharedXml) {
                foreach ($sharedXml->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->si as $item) {
                    $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
                    $sharedStrings[] = implode('', array_map('strval', $parts));
                }
            }

            $sheetPath = $this->firstSheetPath($zip);
            $sheetXml = $this->readZipXml($zip, $sheetPath);
            $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $rows = [];

            $sheetData = $sheetXml->children($mainNamespace)->sheetData;
            foreach ($sheetData->children($mainNamespace)->row as $rowNode) {
                $rowAttributes = $rowNode->attributes();
                $rowNumber = (int) ($rowAttributes['r'] ?? (count($rows) + 1));
                $row = [];
                foreach ($rowNode->children($mainNamespace)->c as $cell) {
                    $attributes = $cell->attributes();
                    $reference = (string) ($attributes['r'] ?? '');
                    if (!preg_match('/^([A-Z]+)/i', $reference, $match)) {
                        continue;
                    }

                    $columnIndex = $this->columnIndex(strtoupper($match[1]));
                    $cellType = (string) ($attributes['t'] ?? '');
                    $cellChildren = $cell->children($mainNamespace);
                    $value = (string) ($cellChildren->v ?? '');

                    if ($cellType === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($cellType === 'inlineStr') {
                        $inline = $cellChildren->is;
                        $parts = $inline ? ($inline->xpath('.//*[local-name()="t"]') ?: []) : [];
                        $value = implode('', array_map('strval', $parts));
                    }

                    $row[$columnIndex] = $this->toUtf8($value);
                }

                if ($row !== []) {
                    ksort($row);
                    $rows[$rowNumber] = $row;
                }

                if (count($rows) > self::MAX_ROWS + 1) {
                    throw new RuntimeException('A planilha excede o limite de '.self::MAX_ROWS.' linhas por importação.');
                }
            }

            if ($rows === []) {
                return [];
            }

            ksort($rows);
            $firstRow = array_key_first($rows);
            $header = $rows[$firstRow];
            $columnCount = max(array_keys($header)) + 1;
            $normalizedRows = [];
            for ($column = 0; $column < $columnCount; $column++) {
                $normalizedRows[0][$column] = $header[$column] ?? '';
            }

            foreach ($rows as $number => $row) {
                if ($number === $firstRow) {
                    continue;
                }
                $normalized = [];
                for ($column = 0; $column < $columnCount; $column++) {
                    $normalized[$column] = $row[$column] ?? '';
                }
                $normalizedRows[] = $normalized;
            }

            return $normalizedRows;
        } finally {
            $zip->close();
        }
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->readZipXml($zip, 'xl/workbook.xml');
        $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $firstSheet = $workbook->children($mainNamespace)->sheets->children($mainNamespace)->sheet[0] ?? null;
        $relationshipId = $firstSheet !== null
            ? (string) ($firstSheet->attributes($relationshipNamespace)['id'] ?? '')
            : '';

        if ($relationshipId === '') {
            throw new RuntimeException('O arquivo XLSX não contém uma primeira aba legível.');
        }

        $relationships = $this->readZipXml($zip, 'xl/_rels/workbook.xml.rels');
        foreach ($relationships->children('http://schemas.openxmlformats.org/package/2006/relationships') as $relationship) {
            $attributes = $relationship->attributes();
            if ((string) ($attributes['Id'] ?? '') !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) ($attributes['Target'] ?? ''), '/');
            $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            if (!str_starts_with($sheetPath, 'xl/worksheets/')) {
                break;
            }

            return $sheetPath;
        }

        throw new RuntimeException('Não foi possível localizar a primeira aba da planilha XLSX.');
    }

    private function readZipXml(ZipArchive $zip, string $name, bool $required = true): ?\SimpleXMLElement
    {
        $index = $zip->locateName($name);
        if ($index === false) {
            if (!$required) {
                return null;
            }
            throw new RuntimeException('O arquivo XLSX está incompleto.');
        }

        $stats = $zip->statIndex($index);
        if (($stats['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new RuntimeException('A planilha XLSX é grande demais para ser processada.');
        }

        $contents = $zip->getFromIndex($index);
        $xml = $contents === false
            ? false
            : simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);

        if (!$xml) {
            if (!$required) {
                return null;
            }
            throw new RuntimeException('Não foi possível interpretar a estrutura do arquivo XLSX.');
        }

        return $xml;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $header = mb_strtolower($header ?? '');

        return str_replace([' ', '-', '_'], '', $header);
    }

    /** @param array<int, string> $headers @param array<int, string> $names */
    private function findHeaderIndex(array $headers, array $names): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array($header, $names, true)) {
                return $index;
            }
        }

        return null;
    }

    private function toUtf8(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $value;
    }

    /** @param array<int, string> $errors */
    private function addError(array &$errors, int $line, string $message): void
    {
        if (count($errors) < 100) {
            $errors[] = "Linha {$line}: {$message}.";
        }
    }
}
