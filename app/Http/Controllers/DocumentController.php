<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFlag;
use App\Models\DocumentFlagWord;
use App\Services\DocumentTextExtractor;
use App\Services\DocxToPdfConverter;
use App\Services\FlagScanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    /** Max upload size in KB */
    protected const MAX_UPLOAD_KB = 10240;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $canManage = auth()->user()->can('docs.document.manage');

        $documents = Document::query()
            ->with('user')
            ->when(!$canManage, function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->when($request->search, function ($query, $search) {
                $query->where('original_name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Docs/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Document::class);

        return Inertia::render('Docs/Create');
    }

    public function store(Request $request, DocumentTextExtractor $extractor, DocxToPdfConverter $converter, FlagScanner $scanner)
    {
        $this->authorize('create', Document::class);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_UPLOAD_KB,
                'mimes:txt,pdf,docx,doc',
            ],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Store on the private local disk (not web-accessible)
        $storedPath = $file->store('documents', 'local');

        $document = Document::create([
            'user_id' => auth()->id(),
            'original_name' => $originalName,
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'size' => $size,
            'status' => 'pending',
            'flag_count' => 0,
        ]);

        // Extract text + scan for flag words
        try {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $isWord = in_array($ext, ['docx', 'doc']) || str_contains(strtolower($mimeType), 'word');

            // For Word documents, convert to PDF first so that pagination
            // matches the original document (LibreOffice has a real layout
            // engine, unlike PHPWord which only splits on explicit page breaks).
            $pdfPreviewPath = null;
            if ($isWord) {
                try {
                    $pdfPreviewPath = $converter->convert('local', $storedPath);
                } catch (\Throwable $e) {
                    // Conversion is best-effort; fall back to PHPWord extraction
                }
            }

            if ($pdfPreviewPath) {
                // Extract per-page text from the converted PDF (accurate pagination)
                $pages = $extractor->extractPdfPerPageFromDisk('local', $pdfPreviewPath);
            } else {
                $pages = $extractor->extractPerPage('local', $storedPath, $mimeType, $originalName);
            }

            $text = implode("\n\n", $pages);

            $flagWords = DocumentFlagWord::query()->pluck('word')->all();
            $perPageScan = $scanner->scanPerPage($pages, $flagWords);

            // Per-page HTML is only needed for Word docs without a PDF preview
            $renderedPages = null;
            if ($isWord && !$pdfPreviewPath) {
                try {
                    $renderedPages = $extractor->extractPerPageHtml('local', $storedPath, $mimeType, $originalName);
                } catch (\Throwable $e) {
                    // HTML rendering is best-effort; continue without it
                }
            }

            $document->update([
                'extracted_text' => $text,
                'rendered_pages' => $renderedPages,
                'pdf_preview_path' => $pdfPreviewPath,
                'status' => 'scanned',
                'flag_count' => $perPageScan['total'],
                'flagged_pages' => $perPageScan['pages'],
            ]);

            // Persist per-flag-word summary (aggregate across pages)
            if ($perPageScan['total'] > 0) {
                // Aggregate word → total occurrences across all pages
                $wordTotals = [];
                foreach ($perPageScan['pages'] as $pageData) {
                    foreach ($pageData['words'] as $w) {
                        $wordTotals[$w['word']] = ($wordTotals[$w['word']] ?? 0) + $w['occurrences'];
                    }
                }

                $wordToId = DocumentFlagWord::query()
                    ->whereIn('word', array_keys($wordTotals))
                    ->pluck('id', 'word');

                $rows = [];
                foreach ($wordTotals as $word => $occurrences) {
                    $flagWordId = $wordToId[$word] ?? null;
                    if (!$flagWordId) {
                        continue;
                    }
                    $rows[] = [
                        'document_id' => $document->id,
                        'flag_word_id' => $flagWordId,
                        'occurrences' => $occurrences,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    DocumentFlag::insert($rows);
                }
            }
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('docs.show', $document->id)
                ->with('warning', 'Document uploaded, but text extraction failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('docs.show', $document->id)
            ->with('success', 'Document uploaded and reviewed.');
    }

    public function show(Request $request, Document $document, FlagScanner $scanner)
    {
        $this->authorize('view', $document);

        $document->load(['user', 'flags.flagWord']);

        // Build the list of flag words that actually appear (for the summary)
        $flaggedWords = $document->flags->map(function ($flag) {
            return [
                'word' => $flag->flagWord->word,
                'occurrences' => $flag->occurrences,
                'suggested_replacement' => $flag->flagWord->suggested_replacement,
            ];
        })->sortByDesc('occurrences')->values()->all();

        // Build a regex pattern the frontend can use to highlight matches in the viewer
        $highlightPattern = $scanner->buildHighlightPattern(
            $document->flags->map(fn ($f) => $f->flagWord->word)->all()
        );

        // Build a word → suggested_replacement map for all flag words so the
        // right panel can display replacements even for words stored only in
        // the document's flagged_pages JSON (which has no replacement column).
        $flagWordMap = DocumentFlagWord::query()
            ->whereNotNull('suggested_replacement')
            ->pluck('suggested_replacement', 'word')
            ->all();

        return Inertia::render('Docs/Show', [
            'document' => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'status' => $document->status,
                'flag_count' => $document->flag_count,
                'flagged_pages' => $document->flagged_pages ?? [],
                'error' => $document->error,
                'extracted_text' => $document->extracted_text,
                'rendered_pages' => $document->rendered_pages,
                'pdf_preview_path' => $document->pdf_preview_path,
                'created_at' => $document->created_at,
                'user' => [
                    'id' => $document->user->id,
                    'name' => $document->user->name,
                ],
            ],
            'flaggedWords' => $flaggedWords,
            'flagWordMap' => $flagWordMap,
            'highlightPattern' => $highlightPattern,
            'can' => [
                'delete' => auth()->user()->can('delete', $document),
                'update' => auth()->user()->can('update', $document),
            ],
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorize('delete', $document);

        $document->delete();

        return redirect()
            ->route('docs.index')
            ->with('success', 'Document deleted.');
    }

    public function rescan(Request $request, Document $document, DocumentTextExtractor $extractor, DocxToPdfConverter $converter, FlagScanner $scanner)
    {
        $this->authorize('update', $document);

        try {
            $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
            $isWord = in_array($ext, ['docx', 'doc']) || str_contains(strtolower($document->mime_type), 'word');

            // Re-convert Word documents to PDF for accurate pagination
            $pdfPreviewPath = $document->pdf_preview_path;
            if ($isWord) {
                // Delete old preview if it exists so we get a fresh conversion
                if ($pdfPreviewPath) {
                    Storage::disk('local')->delete($pdfPreviewPath);
                    $pdfPreviewPath = null;
                }
                try {
                    $pdfPreviewPath = $converter->convert('local', $document->file_path);
                } catch (\Throwable $e) {
                    // Conversion is best-effort; fall back to PHPWord extraction
                }
            }

            if ($pdfPreviewPath) {
                $pages = $extractor->extractPdfPerPageFromDisk('local', $pdfPreviewPath);
            } else {
                $pages = $extractor->extractPerPage('local', $document->file_path, $document->mime_type, $document->original_name);
            }

            $text = implode("\n\n", $pages);

            $flagWords = DocumentFlagWord::query()->pluck('word')->all();
            $perPageScan = $scanner->scanPerPage($pages, $flagWords);

            // Regenerate per-page HTML only for Word docs without a PDF preview
            $renderedPages = null;
            if ($isWord && !$pdfPreviewPath) {
                try {
                    $renderedPages = $extractor->extractPerPageHtml('local', $document->file_path, $document->mime_type, $document->original_name);
                } catch (\Throwable $e) {
                    // HTML rendering is best-effort; continue without it
                }
            }

            // Remove old flag records
            $document->flags()->delete();

            $document->update([
                'extracted_text' => $text,
                'rendered_pages' => $renderedPages,
                'pdf_preview_path' => $pdfPreviewPath,
                'status' => 'scanned',
                'flag_count' => $perPageScan['total'],
                'flagged_pages' => $perPageScan['pages'],
                'error' => null,
            ]);

            // Persist per-flag-word summary (aggregate across pages)
            if ($perPageScan['total'] > 0) {
                $wordTotals = [];
                foreach ($perPageScan['pages'] as $pageData) {
                    foreach ($pageData['words'] as $w) {
                        $wordTotals[$w['word']] = ($wordTotals[$w['word']] ?? 0) + $w['occurrences'];
                    }
                }

                $wordToId = DocumentFlagWord::query()
                    ->whereIn('word', array_keys($wordTotals))
                    ->pluck('id', 'word');

                $rows = [];
                foreach ($wordTotals as $word => $occurrences) {
                    $flagWordId = $wordToId[$word] ?? null;
                    if (!$flagWordId) {
                        continue;
                    }
                    $rows[] = [
                        'document_id' => $document->id,
                        'flag_word_id' => $flagWordId,
                        'occurrences' => $occurrences,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    DocumentFlag::insert($rows);
                }
            }
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('docs.show', $document->id)
                ->with('warning', 'Rescan failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('docs.show', $document->id)
            ->with('success', 'Document rescanned successfully.');
    }

    /**
     * Stream the private file to the browser (gated by view policy).
     */
    public function download(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('local')->get($document->file_path);
        }, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * Stream the generated PDF preview (for Word documents) to the browser.
     */
    public function pdfPreview(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        if (!$document->pdf_preview_path || !Storage::disk('local')->exists($document->pdf_preview_path)) {
            abort(404, 'PDF preview not available.');
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('local')->get($document->pdf_preview_path);
        }, pathinfo($document->original_name, PATHINFO_FILENAME) . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
