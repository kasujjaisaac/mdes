<?php

namespace App\Services;

use App\Models\ManagedDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentStorageService
{
    public function store(array $data, UploadedFile $file, User $user): ManagedDocument
    {
        $path = $this->writeFile($file);
        try {
            return DB::transaction(function () use ($data, $file, $user, $path) {
                $document = ManagedDocument::create([...$data, 'uploaded_by' => $user->id, 'uploaded_at' => now()]);
                $this->recordVersion($document, $file, $user, $path, 'initial upload');

                return $document;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function attachVersion(ManagedDocument $document, UploadedFile $file, User $user, ?string $reason = null): void
    {
        // Keep the old version current until both the file and replacement record exist.
        $path = $this->writeFile($file);
        try {
            DB::transaction(function () use ($document, $file, $user, $path, $reason) {
                $locked = ManagedDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $this->recordVersion($locked, $file, $user, $path, $reason);
            });
            $document->unsetRelation('currentVersion');
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    private function writeFile(UploadedFile $file): string
    {
        $path = $file->store('mdsiss-documents', 'local');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store document.');
        }

        return $path;
    }

    private function recordVersion(ManagedDocument $document, UploadedFile $file, User $user, string $path, ?string $reason): void
    {
        $version = (int) $document->versions()->max('version') + 1;
        $document->versions()->update(['is_current' => false]);
        $document->versions()->create([
            'version' => $version, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0, 'reason' => $reason, 'is_current' => true,
            'uploaded_by' => $user->id, 'uploaded_at' => now(),
        ]);
    }

    public function exists(ManagedDocument $document): bool
    {
        $version = $document->currentVersion;

        return $version !== null && Storage::disk($version->disk)->exists($version->path);
    }
}
