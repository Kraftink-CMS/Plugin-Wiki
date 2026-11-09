@use('KraftInk\Plugins\Wiki\Services\Documents')
<nav aria-label="{{ __('wiki::messages.editor.contents') }}" class="card site-panel wiki-navigation">
    <div class="card-header fw-semibold py-3">
        <a class="d-none d-lg-inline" href="{{ route('plugins.wiki.public.documents.index') }}">
            <i class="bi bi-journal-text me-2 text-primary" aria-hidden="true"></i>{{ __('wiki::messages.editor.contents') }}
        </a>
        <button class="btn btn-link d-flex d-lg-none align-items-center gap-2 w-100 p-0 text-decoration-none"
            type="button" data-bs-toggle="collapse" data-bs-target="#wiki-contents" aria-expanded="false"
            aria-controls="wiki-contents">
            <i class="bi bi-journal-text" aria-hidden="true"></i>{{ __('wiki::messages.editor.contents') }}
            <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
        </button>
    </div>
    <div class="collapse d-lg-block" id="wiki-contents">
        <div class="card-body py-2">
            <a class="d-inline-block d-lg-none my-2" href="{{ route('plugins.wiki.public.documents.index') }}">{{ __('wiki::messages.common.wiki') }}</a>
            @if (isset($navigation['documents'][0]))
                <ul class="list-unstyled mb-2">
                    @foreach ($navigation['documents'][0] as $item)
                        <li class="py-1"><a @class([
                            'd-block rounded px-2 py-1 text-decoration-none',
                            'fw-semibold' => isset($document) && $document->id === $item->id,
                            'bg-primary-subtle' => isset($document) && $document->id === $item->id,
                        ])
                                @if (isset($document) && $document->id === $item->id) aria-current="page" @endif
                                data-wiki-article-link
                                href="{{ app(Documents::class)->url($item) }}">{{ $item->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @include('wiki::default.documents.partials.navigation_tree', ['nodes' => $navigation['tree']])
        </div>
    </div>
</nav>
