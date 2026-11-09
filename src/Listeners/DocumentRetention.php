<?php

namespace KraftInk\Plugins\Wiki\Listeners;

use App\Events\PruningData;
use App\Kraft\System\RevisionRetention;
use KraftInk\Plugins\Wiki\Models\Document;
use KraftInk\Plugins\Wiki\Models\DocumentRevision;
use KraftInk\Plugins\Wiki\Services\DocumentImages;

class DocumentRetention
{
    public function prune(PruningData $event): void
    {
        $event->add(
            'wiki.history',
            app(RevisionRetention::class)->prune(DocumentRevision::query(), 'document_id', $event->dryRun),
        );

        if (!$event->dryRun) {
            $references = 0;
            Document::query()
                ->orderBy('id')
                ->chunkById(100, function ($documents) use (&$references) {
                    foreach ($documents as $document) {
                        $references += app(DocumentImages::class)->retain($document->id);
                    }
                });
            $event->add('wiki.image_references', $references);
        }
        $event->add('wiki.images', app(DocumentImages::class)->cleanup(dryRun: $event->dryRun));
    }
}
