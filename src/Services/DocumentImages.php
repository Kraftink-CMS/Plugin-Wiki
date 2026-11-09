<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Kraft\LocalePolicy;
use App\Kraft\Media\EditorImageScope;
use App\Kraft\Media\PrivateEditorImages;
use App\Models\Accounts\User;
use KraftInk\Plugins\Wiki\Models\Document;
use KraftInk\Plugins\Wiki\Models\DocumentImage;
use KraftInk\Plugins\Wiki\Models\DocumentRevision;
use KraftInk\Plugins\Wiki\Models\Image;

class DocumentImages extends PrivateEditorImages
{
    public function __construct()
    {
        parent::__construct(
            new EditorImageScope(
                'wiki',
                Image::class,
                DocumentImage::class,
                Document::class,
                'plugins.wiki.public.images.show',
                'wiki.manage',
                fn (?User $user) => app(Documents::class)
                    ->readable($user)
                    ->whereIn(
                        'locale',
                        app(LocalePolicy::class)->allowsChoice()
                            ? array_keys(config('kraft.locales'))
                            : [app(LocalePolicy::class)->defaultLocale()],
                    ),
                history: fn () => DocumentRevision::query()->select(
                    'document_id as resource_id',
                    'snapshot->body as body',
                ),
            ),
        );
    }
}
