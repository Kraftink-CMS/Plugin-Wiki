@use('App\Kraft\Site\Seo')
@use('KraftInk\Plugins\Wiki\Services\Documents')
@extends('default.layouts.layout')
@section('content')
    <div class="row g-4">
        <aside class="col-lg-3 align-self-start">
            @include('wiki::default.documents.partials.navigation')
        </aside>
        <section class="col-lg-9">
            <header class="page-heading mt-0">
                <h1 class="h2 mb-2">{{ $category?->name ?? __('wiki::messages.common.wiki') }}</h1>
                @if ($category?->description)
                    <p class="text-body-secondary mb-0">{{ $category->description }}</p>
                @endif
            </header>
            <form method="get" class="card card-body site-panel mb-4">
                @if ($category)
                    <input type="hidden" name="category" value="{{ $category->id }}">
                @endif
                <label class="form-label" for="wiki-search">
                    {{ __('wiki::messages.common.search') }}</label>
                <div class="input-group">
                    <input class="form-control" type="search" id="wiki-search" name="q" value="{{ request('q') }}"
                        maxlength="100">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-2"
                            aria-hidden="true"></i>{{ __('wiki::messages.common.search') }}</button>
                </div>
                @if (request()->filled('q'))
                    <a class="small mt-2 align-self-start" href="{{ route('plugins.wiki.public.documents.index', $category ? ['category' => $category->id] : []) }}">{{ __('wiki::messages.common.reset_search') }}</a>
                @endif
            </form>
            <div id="wiki-public-documents" data-ajax-pagination tabindex="-1" class="vstack gap-4">
                @forelse($documents->groupBy('category_id') as $items)
                    <section class="mb-4">
                        <h2 class="h5 section-heading mb-3">{{ $items->first()->category?->name ?? __('wiki::messages.common.general') }}</h2>
                        <div class="row g-3">
                            @foreach ($items as $item)
                                <div class="col-md-6">
                                    <article class="card content-card h-100">
                                        <div class="card-body p-4">
                                            <i class="bi bi-file-earmark-text fs-4 text-primary d-block mb-3"
                                                aria-hidden="true"></i>
                                            <h3 class="h5"><a class="text-decoration-none"
                                                    href="{{ app(Documents::class)->url($item) }}">{{ $item->title }}</a>
                                            </h3>
                                            <p class="small text-body-secondary mb-0">
                                                {{ Seo::excerpt($item->body, 170) }}</p>
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="card card-body empty-state text-center">
                        <i class="bi bi-journal-text fs-2 mb-3" aria-hidden="true"></i>
                        <p class="mb-0">{{ __(request()->filled('q') ? 'wiki::messages.common.no_results' : 'wiki::messages.common.empty') }}</p>
                    </div>
                @endforelse
                {{ $documents->links() }}
            </div>
        </section>
    </div>
@endsection
