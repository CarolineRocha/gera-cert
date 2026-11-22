<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Services\ParticipantImportService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Support\CreatesApplicationRecords;
use Tests\TestCase;
use ZipArchive;

class ParticipantImportFlowTest extends TestCase
{
    use CreatesApplicationRecords;
    use DatabaseMigrations;

    public function test_csv_import_adds_valid_participants_and_reports_invalid_and_duplicate_rows(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $this->createParticipant($project, 'Already Here', 'existing@example.test');
        $csv = implode("\r\n", [
            'nome,email',
            'Ana Silva,ana@example.test',
            'Sem Email,not-an-email',
            'Duplicada,ana@example.test',
            'Já Existe,existing@example.test',
            ',',
        ]);

        $response = $this->actingAs($admin)->post(route('participants.import.store'), [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('lista.csv', $csv),
        ]);

        $response->assertRedirect(route('participants.import.form'));
        $response->assertSessionHas('import_result', function (array $result): bool {
            return $result['added'] === 1
                && $result['rejected'] === 3
                && count($result['errors']) === 3;
        });
        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseHas('participants', [
            'project_id' => $project->id,
            'name' => 'Ana Silva',
            'email' => 'ana@example.test',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'participants.imported',
        ]);
    }

    public function test_xlsx_import_reads_first_sheet_and_accepts_english_headers(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $file = UploadedFile::fake()->createWithContent('lista.xlsx', $this->makeXlsx([
            ['name', 'e-mail'],
            ['Rita Teste', 'rita@example.test'],
        ]));

        $this->from(route('participants.import.form'))
            ->actingAs($admin)
            ->post(route('participants.import.store'), [
            'project_id' => $project->id,
            'file' => $file,
        ])->assertRedirect(route('participants.import.form'));

        $this->assertDatabaseHas('participants', [
            'project_id' => $project->id,
            'name' => 'Rita Teste',
            'email' => 'rita@example.test',
        ]);
    }

    public function test_import_cannot_write_to_another_administrators_project(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $foreignProject = $this->createProject($otherAdmin, 'Projeto alheio');

        $this->actingAs($admin)->post(route('participants.import.store'), [
            'project_id' => $foreignProject->id,
            'file' => UploadedFile::fake()->createWithContent('lista.csv', "nome,email\nAna,ana@example.test\n"),
        ])->assertNotFound();

        $this->assertDatabaseCount('participants', 0);
    }

    public function test_import_rejects_unknown_format_and_invalid_headers(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);

        $this->actingAs($admin)->from(route('participants.import.form'))->post(route('participants.import.store'), [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('lista.json', '{"name":"Ana"}'),
        ])->assertRedirect(route('participants.import.form'))
            ->assertSessionHasErrors('file');

        $this->from(route('participants.import.form'))->post(route('participants.import.store'), [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('lista.csv', "nome,telefone\nAna,555\n"),
        ])->assertRedirect(route('participants.import.form'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('participants', 0);
    }

    public function test_template_download_contains_expected_column_headers(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('participants.import.template'));

        $response->assertOk()->assertDownload('modelo-importacao-participantes.csv');
        $this->assertStringContainsString('nome,email', $response->streamedContent());
    }

    /** @param array<int, array<int, string>> $rows */
    private function makeXlsx(array $rows): string
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('A extensão PHP zip é necessária para ler XLSX.');
        }

        $strings = [];
        $sheetRows = [];
        foreach ($rows as $rowNumber => $values) {
            $cells = [];
            foreach ($values as $column => $value) {
                $stringIndex = count($strings);
                $strings[] = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $columnName = chr(65 + $column);
                $cells[] = '<c r="'.$columnName.($rowNumber + 1).'" t="s"><v>'.$stringIndex.'</v></c>';
            }
            $sheetRows[] = '<row r="'.($rowNumber + 1).'">'.implode('', $cells).'</row>';
        }

        $xlsxPath = tempnam(sys_get_temp_dir(), 'gera-cert-xlsx-');
        $zip = new ZipArchive();
        $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Lista" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.implode('', array_map(fn ($string) => '<si><t>'.$string.'</t></si>', $strings)).'</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->close();
        $contents = file_get_contents($xlsxPath);
        unlink($xlsxPath);

        return $contents;
    }
}
