@use('KraftInk\Plugins\Wiki\Services\Documents')
@foreach ($nodes as $node)
    <details class="py-2" @if (in_array($node['category']->id, $navigation['expanded'], true)) open @endif>
        <summary class="fw-medium">
            @if (class_exists(\App\Kraft\ContentIcons::class))
                <x-dynamic-component component="content-icon" :name="$node['category']->icon" class="me-1 text-body-secondary" />
            @else
                <i class="bi bi-{{ $node['category']->icon }} me-1 text-body-secondary" aria-hidden="true"></i>
            @endif
            {{ $node['category']->name }}
        </summary>
        <div class="ps-3 border-start ms-1">
            <a class="small d-block py-2" href="{{ route('plugins.wiki.public.documents.index', ['category' => $node['category']->id]) }}">{{ __('wiki::messages.common.view_category') }}</a>
            @if (isset($navigation['documents'][$node['category']->id]))
                <ul class="list-unstyled my-2">
                    @foreach ($navigation['documents'][$node['category']->id] as $item)
                        <li class="py-1"><a @class([
                            'd-block rounded px-2 py-1 text-decoration-none small',
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
            @include('wiki::default.documents.partials.navigation_tree', ['nodes' => $node['children']])
        </div>
    </details>
@endforeach
