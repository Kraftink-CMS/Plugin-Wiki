<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Kraft\LocalePolicy;
use App\Kraft\Media\FileDeletionQueue;
use App\Models\Accounts\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Wiki\Models\Document;
use KraftInk\Plugins\Wiki\Models\FileAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DocumentFiles
{
    private const array Types = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
        'application/x-zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
    ];

    public function store(User $actor, int $documentId, UploadedFile $file): FileAttachment
    {
        $mime = $file->getMimeType();
        if (!$file->isValid() || !isset(self::Types[$mime]) || $file->getSize() > 10485760) {
            throw ValidationException::withMessages(['file' => __('wiki::messages.files.invalid')]);
        }

        return DB::transaction(function () use ($actor, $documentId, $file, $mime): FileAttachment {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $document = $this->lockDocument($documentId);
            $document->touch();
            $checksum = hash_file('sha256', $file->getRealPath());
            $existing = $document->files()->where('checksum', $checksum)->first();
            if ($existing && $this->safePath($existing->path) && Storage::disk('local')->exists($existing->path)) {
                return $existing;
            }
            if (
                $document->files()->count() >= 20 ||
                FileAttachment::query()->where('uploader_id', $actor->id)->sum('size') + $file->getSize() > 268435456
            ) {
                throw ValidationException::withMessages(['file' => __('wiki::messages.files.quota')]);
            }
            $id = (string) Str::uuid();
            $path = 'wiki/files/'.$id.'.'.self::Types[$mime];
            DB::afterRollBack(fn () => $this->scheduleDeletion($path));
            if (
                !Storage::disk('local')->putFileAs('wiki/files', $file, basename($path), [
                    'visibility' => 'private',
                ])
            ) {
                throw new RuntimeException(__('wiki::messages.files.failed'));
            }
            $name = mb_substr(
                preg_replace('/[\\\\\/\p{C}]/u', '_', $file->getClientOriginalName()) ?:
                'document.'.self::Types[$mime],
                0,
                180,
            );
            $attachment = new FileAttachment([
                'uploader_id' => $actor->id,
                'path' => $path,
                'name' => $name,
                'mime' => $mime,
                'size' => $file->getSize(),
                'checksum' => $checksum,
            ]);
            $attachment->id = $id;
            $document->files()->save($attachment);

            return $attachment;
        }, 3);
    }

    public function download(string $id, ?User $user, bool $admin = false): StreamedResponse
    {
        $file = FileAttachment::query()->find($id);
        if (!($file && $this->safePath($file->path) && Storage::disk('local')->exists($file->path))) {
            abort(404);
        }
        if ($admin) {
            if (!($user?->hasPermission('admin.access') && $user->hasPermission('wiki.manage'))) {
                throw new HttpException(403);
            }
            if (!Document::query()->where('id', $file->document_id)->exists()) {
                abort(404);
            }
        } else {
            $policy = app(LocalePolicy::class);
            if (
                !app(Documents::class)
                    ->readable($user)
                    ->whereIn(
                        'locale',
                        $policy->allowsChoice() ? array_keys(config('kraft.locales')) : [$policy->defaultLocale()],
                    )
                    ->where('id', $file->document_id)
                    ->exists()
            ) {
                abort(404);
            }
        }

        return Storage::disk('local')->download($file->path, $file->name, [
            'Content-Type' => $file->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function delete(int $documentId, string $fileId): void
    {
        DB::transaction(function () use ($documentId, $fileId): void {
            $document = $this->lockDocument($documentId);
            $file = $document->files()->lockForUpdate()->find($fileId);
            if ($file === null) {
                abort(404);
            }

            $this->deleteAttachment($file);
            $document->touch();
        }, 3);
    }

    public function deleteWhere(callable $scope): void
    {
        $query = FileAttachment::query();
        $scope($query);
        foreach ($query->lockForUpdate()->get(['id', 'path']) as $file) {
            $this->deleteAttachment($file);
        }
    }

    private function lockDocument(int $id): Document
    {
        $document = Document::query()->whereNull('deleted_at')->lockForUpdate()->find($id);
        if ($document === null) {
            abort(404);
        }

        return $document;
    }

    private function deleteAttachment(FileAttachment $file): void
    {
        $file->delete();
        $this->scheduleDeletion($file->path);
    }

    private function scheduleDeletion(string $path): void
    {
        if ($this->safePath($path)) {
            app(FileDeletionQueue::class)->schedule($path, 'wiki/files', ['wiki__files' => 'path']);
        }
    }

    private function safePath(string $path): bool
    {
        return preg_match('~^wiki/files/[a-f0-9-]{36}\.(?:pdf|txt|zip)$~D', $path) === 1;
    }
}
