<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Kraft\Audit;
use App\Kraft\Trash\TrashRegistry;
use App\Kraft\Trash\TrashSource;
use App\Models\Accounts\User;
use Illuminate\Support\Facades\DB;
use KraftInk\Plugins\Wiki\Models\Document;

class DocumentTrash
{
    public function restore(User $actor, int $id): void
    {
        DB::transaction(function () use ($id) {
            $document = Document::query()->where('id', $id)->whereNotNull('deleted_at')->lockForUpdate()->first();
            if ($document === null) {
                abort(404);
            }
            app(Documents::class)->assertAvailableSlug($document->locale, $document->slug, $document->category_id, $id);
            Document::query()
                ->where('id', $id)
                ->update(['deleted_at' => null, 'updated_at' => now()]);
        }, 3);
    }

    public function purge(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $document = Document::query()->where('id', $id)->whereNotNull('deleted_at')->lockForUpdate()->first();
            if ($document === null) {
                abort(404);
            }
            app(DocumentFiles::class)->deleteWhere(fn ($query) => $query->where('document_id', $id));
            app(DocumentImages::class)->purge($id);
            Document::query()->where('id', $id)->delete();
            Audit::record('wiki.document_purged', ['id' => $id], $actor);
        }, 3);
    }

    public function register(TrashRegistry $registry): void
    {
        $registry->register(
            new TrashSource(
                'wiki.documents',
                'wiki::messages.common.wiki',
                ['wiki.manage'],
                ['wiki.manage'],
                fn (User $user) => Document::query()
                    ->whereNotNull('deleted_at')
                    ->select(['id', 'title', 'deleted_at'])
                    ->selectRaw('NULL AS restore_reason'),
                fn (User $actor, int $id) => app(DocumentTrash::class)->restore($actor, $id),
                purgePermissions: ['wiki.purge'],
                purge: fn (User $actor, int $id) => app(DocumentTrash::class)->purge($actor, $id),
            ),
        );
    }
}
