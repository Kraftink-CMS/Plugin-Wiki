@use('KraftInk\Plugins\Wiki\Services\Documents')
<section class="card h-100">
    <div class="card-body">
        <h2 class="h5 card-title mb-3">{{ __('wiki::widgets.recent_documents') }}</h2>
        <ul class="list-unstyled mb-0">
            @forelse ($documents as $document)
                <li class="mb-2">
                    <a href="{{ app(Documents::class)->url($document) }}">{{ $document->title }}</a>
                </li>
            @empty
                <li class="text-body-secondary">{{ __('wiki::widgets.empty') }}</li>
            @endforelse
        </ul>
    </div>
</section>
