<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\DocumentCategory;
use App\Models\ManagedDocument;
use App\Models\School;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('documents.view');

        return view('documents.index', [
            'title' => 'documents',
            'breadcrumbs' => [['label' => 'documents']],
            'documents' => ManagedDocument::query()->visibleTo(auth()->user())->with(['category', 'school', 'currentVersion'])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('documents.manage');

        return view('documents.create', [
            'title' => 'upload document',
            'breadcrumbs' => [['label' => 'documents', 'url' => route('documents.index')], ['label' => 'upload']],
            'categories' => DocumentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'schools' => School::query()->visibleTo(auth()->user())->orderBy('official_name')->get(),
        ]);
    }

    public function store(StoreDocumentRequest $request, DocumentStorageService $service): RedirectResponse
    {
        Gate::authorize('documents.manage');

        if ($request->filled('school_id')) {
            Gate::authorize('view', School::findOrFail($request->integer('school_id')));
        }
        $service->store($request->safe()->except('file'), $request->file('file'), $request->user());

        return redirect()->route('documents.index')->with('status', 'document uploaded to protected storage.');
    }

    public function download(ManagedDocument $document): StreamedResponse
    {
        Gate::authorize('documents.view');

        Gate::authorize('view', $document);
        $version = $document->loadMissing('currentVersion')->currentVersion;
        abort_unless($version, 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }
}
