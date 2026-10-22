<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class DocxTemplateInspector
{
    /** @return list<string> */
    public function inspect(UploadedFile $file, string $permitType): array
    {
        $path = $file->getRealPath();
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path) !== true) {
            $this->invalid('File bukan dokumen DOCX yang valid.');
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                $this->invalid('Struktur DOCX tidak lengkap. Simpan ulang template melalui Microsoft Word.');
            }

            $uncompressed = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $uncompressed += (int) ($stat['size'] ?? 0);
                if ($uncompressed > (int) config('document-templates.max_uncompressed_bytes')) {
                    $this->invalid('Isi DOCX terlalu besar setelah diekstrak.');
                }
                if (str_contains(strtolower($name), 'vbaproject.bin')) {
                    $this->invalid('Template bermacro tidak diizinkan. Gunakan DOCX tanpa macro.');
                }
                if (str_ends_with($name, '.rels')) {
                    $relations = (string) $zip->getFromIndex($index);
                    if (preg_match('/TargetMode=["\']External["\']/i', $relations) === 1) {
                        $this->invalid('Template tidak boleh memuat tautan atau resource eksternal.');
                    }
                }
            }
        } finally {
            $zip->close();
        }

        try {
            $placeholders = array_values(array_unique((new TemplateProcessor($path))->getVariables()));
        } catch (Throwable) {
            $this->invalid('Placeholder DOCX tidak dapat dibaca. Simpan ulang template melalui Microsoft Word.');
        }
        if (! in_array('permit_number', $placeholders, true)) {
            $this->invalid('Template wajib memiliki placeholder ${permit_number}.');
        }

        $supported = array_merge(
            config('document-templates.common_placeholders', []),
            config("document-templates.placeholders.{$permitType}", []),
        );
        $unknown = array_values(array_diff($placeholders, $supported));
        if ($unknown !== []) {
            $this->invalid('Placeholder tidak dikenali: '.implode(', ', array_map(fn (string $item): string => '${'.$item.'}', $unknown)).'.');
        }

        return $placeholders;
    }

    /** @return list<string> */
    public function supportedFor(string $permitType): array
    {
        abort_unless(in_array($permitType, DocumentTemplate::PERMIT_TYPES, true), 404);

        return array_merge(
            config('document-templates.common_placeholders', []),
            config("document-templates.placeholders.{$permitType}", []),
        );
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['template' => $message]);
    }
}
