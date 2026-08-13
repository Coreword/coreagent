<?php

namespace App\Services\DocumentPipeline;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class IngestService
{
    /**
     * @return array<int, array{page_number: ?int, text: string}>
     */
    public function extractPages(Document $document): array
    {
        $absolutePath = Storage::path($document->storage_path);

        $pages = match ($document->mime_type) {
            'application/pdf' => $this->extractPdf($absolutePath),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->extractDocx($absolutePath),
            default => $this->extractPlainText($absolutePath),
        };

        AuditLog::record($document->case_id, 'system', null, 'document_ingested', [
            'document_id' => $document->id,
            'page_count' => count($pages),
        ]);

        return $pages;
    }

    /**
     * @return array<int, array{page_number: ?int, text: string}>
     */
    protected function extractPdf(string $path): array
    {
        $result = Process::run(['pdftotext', '-layout', $path, '-']);

        if (! $result->successful()) {
            throw new RuntimeException("pdftotext failed: {$result->errorOutput()}");
        }

        // pdftotext separates pages with a form-feed character (0x0C) by default.
        $rawPages = explode("\f", $result->output());

        $pages = [];
        foreach ($rawPages as $index => $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $pages[] = ['page_number' => $index + 1, 'text' => $text];
        }

        return $pages;
    }

    /**
     * @return array<int, array{page_number: ?int, text: string}>
     */
    protected function extractDocx(string $path): array
    {
        $phpWord = IOFactory::load($path);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText()."\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $inner) {
                        if (method_exists($inner, 'getText')) {
                            $text .= $inner->getText()."\n";
                        }
                    }
                }
            }
        }

        $text = trim($text);

        // DOCX has no fixed page breaks in the object model, so it's treated as a single page.
        return $text === '' ? [] : [['page_number' => null, 'text' => $text]];
    }

    /**
     * @return array<int, array{page_number: ?int, text: string}>
     */
    protected function extractPlainText(string $path): array
    {
        $text = trim(file_get_contents($path) ?: '');

        return $text === '' ? [] : [['page_number' => 1, 'text' => $text]];
    }
}
