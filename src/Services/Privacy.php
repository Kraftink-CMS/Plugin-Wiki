<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Events\AccountDeleting;
use App\Events\ExportingPersonalData;
use App\Kraft\Extensions\Contracts\PrivacyHandler;
use KraftInk\Plugins\Wiki\Models\DocumentRevision;
use KraftInk\Plugins\Wiki\Models\FileAttachment;
use KraftInk\Plugins\Wiki\Models\Image;

class Privacy implements PrivacyHandler
{
    public function export(ExportingPersonalData $event): void
    {
        $canManage = $event->user->hasPermission('wiki.manage');
        $event->data['wiki'] = [
            'files' => FileAttachment::query()
                ->where('uploader_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'document_id', 'name', 'mime', 'size', 'created_at'])
                ->cursor(),
            'images' => Image::query()
                ->where('uploader_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'original_name', 'mime', 'size', 'width', 'height', 'created_at'])
                ->cursor(),
            'revisions' => DocumentRevision::query()
                ->where('user_id', $event->user->id)
                ->orderBy('id')
                ->select(['id', 'document_id', 'snapshot', 'created_at'])
                ->cursor()
                ->map(function ($revision) use ($canManage) {
                    $revision->snapshot = $canManage ? $revision->snapshot : null;

                    return $revision;
                }),
        ];
    }

    public function anonymize(AccountDeleting $event): void
    {
        app(DocumentFiles::class)->deleteWhere(fn ($query) => $query->where('uploader_id', $event->user->id));
        app(DocumentImages::class)->anonymizeUser($event->user->id);
        DocumentRevision::query()
            ->where('user_id', $event->user->id)
            ->orderBy('id')
            ->select(['id', 'document_id'])
            ->chunkById(100, function ($revisions) {
                foreach ($revisions as $revision) {
                    DocumentRevision::query()
                        ->where('id', $revision->id)
                        ->update([
                            'user_id' => null,
                            'snapshot' => json_encode(
                                ['anonymized' => true, 'document_id' => $revision->document_id],
                                JSON_THROW_ON_ERROR,
                            ),
                        ]);
                }
            });
    }
}
