<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        $type = $request->query('type');
        $projectId = $request->query('project_id');
        $workerId = $request->query('worker_id');

        $query = Document::query()
            ->with(['project:id,name', 'worker:id,name', 'uploader:id,name'])
            ->orderByDesc('id');

        if ($type && in_array($type, Document::TYPES, true)) {
            $query->where('type', $type);
        }
        if ($projectId) {
            $query->where('project_id', (int) $projectId);
        }
        if ($workerId) {
            $query->where('worker_id', (int) $workerId);
        }

        $documents = $query->get();

        $grouped = $documents
            ->groupBy('type')
            ->map(fn ($items, $key) => [
                'type' => $key,
                'label' => str_replace('_', ' ', (string) $key),
                'documents' => $items->values(),
            ])
            ->values();

        return Inertia::render('Documents/Gallery', [
            'documents' => $documents,
            'grouped' => $grouped,
            'filters' => [
                'type' => $type && in_array($type, Document::TYPES, true) ? $type : null,
                'project_id' => $projectId ? (int) $projectId : null,
                'worker_id' => $workerId ? (int) $workerId : null,
            ],
            'types' => Document::TYPES,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validated();
        $file = $request->file('file');
        $projectId = (int) $data['project_id'];
        $type = (string) $data['type'];

        $directory = sprintf('%d/%s', $projectId, $type);
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, Document::DISK);

        Document::query()->create([
            'project_id' => $projectId,
            'worker_id' => $data['worker_id'] ?? null,
            'type' => $type,
            'title' => $data['title'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('documents.index', array_filter([
                'project_id' => $projectId,
                'type' => $type,
                'worker_id' => $data['worker_id'] ?? null,
            ]))
            ->with('success', 'Document uploaded.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        if ($document->path && Storage::disk(Document::DISK)->exists($document->path)) {
            Storage::disk(Document::DISK)->delete($document->path);
        }

        $filters = array_filter([
            'project_id' => $document->project_id,
            'type' => $document->type,
            'worker_id' => $document->worker_id,
        ]);

        $document->delete();

        return redirect()
            ->route('documents.index', $filters)
            ->with('success', 'Document deleted.');
    }

    public function file(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(
            Storage::disk(Document::DISK)->exists($document->path),
            404,
        );

        return Storage::disk(Document::DISK)->response(
            $document->path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            ],
        );
    }
}
