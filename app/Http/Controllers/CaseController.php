<?php

namespace App\Http\Controllers;

use App\Jobs\IngestDocumentJob;
use App\Models\CaseRecord;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cases/Index', [
            'cases' => CaseRecord::withCount('documents')->latest()->get(),
            'verticals' => array_keys(config('schemas')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vertical' => 'required|string',
            'reference' => 'nullable|string|max:100',
            'document' => 'required|file|mimes:pdf,docx,txt|max:20480',
        ]);

        $case = CaseRecord::create([
            'vertical' => $validated['vertical'],
            'reference' => $validated['reference'] ?? null,
        ]);

        $this->attachDocument($case, $request);

        return redirect()->route('cases.show', $case);
    }

    public function show(CaseRecord $case): Response
    {
        return Inertia::render('Cases/Show', [
            'case' => $case->load(['documents', 'extractions', 'findings', 'auditLogs' => fn ($q) => $q->latest()->limit(50)]),
        ]);
    }

    public function addDocument(Request $request, CaseRecord $case): RedirectResponse
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,docx,txt|max:20480',
        ]);

        $this->attachDocument($case, $request);

        return redirect()->route('cases.show', $case);
    }

    protected function attachDocument(CaseRecord $case, Request $request): Document
    {
        $file = $request->file('document');
        $path = $file->store('documents');

        $document = Document::create([
            'case_id' => $case->id,
            'filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
        ]);

        IngestDocumentJob::dispatch($document);

        return $document;
    }
}
