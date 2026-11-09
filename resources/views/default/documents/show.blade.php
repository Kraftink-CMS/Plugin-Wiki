@use('App\Kraft\SiteDates')
@use('KraftInk\Plugins\Wiki\Services\Documents')
@extends('default.layouts.layout')
@section('content')
    <div class="row g-4" data-wiki-article>
        <aside class="col-lg-3 align-self-start">
            @include('wiki::default.documents.partials.navigation')
        </aside>
        <div class="col-lg-9">
            <article class="card card-body site-panel p-4 p-md-5">
                <nav aria-label="{{ __('wiki::messages.editor.breadcrumb') }}">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a
                                href="{{ route('plugins.wiki.public.documents.index') }}">{{ __('wiki::messages.common.wiki') }}</a>
                        </li>
                        @if ($category)
                            <li class="breadcrumb-item"><a
                                    href="{{ route('plugins.wiki.public.documents.index', ['category' => $category->id]) }}">{{ $category->name }}</a>
                            </li>
                        @endif
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ $document->title }}</li>
                    </ol>
                </nav>
                @if ($translations->count() > 1)
                    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="{{ __('wiki::messages.editor.available_languages') }}">
                        @foreach ($translations as $translation)
                            <a @class([
                                'btn btn-sm',
                                'btn-primary' => $translation->locale === $document->locale,
                                'btn-outline-secondary' => $translation->locale !== $document->locale,
                            ])
                                href="{{ app(Documents::class)->url($translation) }}"
                                lang="{{ $translation->locale }}"
                                hreflang="{{ $translation->locale }}">{{ config('kraft.locales')[$translation->locale] }}</a>
                        @endforeach
                    </nav>
                @endif
                <h1 class="h2 mb-4" tabindex="-1">{{ $document->title }}</h1>
                <div class="prose">
                    {!! $document->body !!}
                </div>
                <p class="text-body-secondary mt-4"><small>{{ __('wiki::messages.common.updated') }}
                        {{ app(SiteDates::class)->format($document->updated_at, 'd F Y') }}</small>
                </p>
                @if ($files->isNotEmpty())
                    <section class="mt-4 mb-4">
                        <h2 class="h5 section-heading">{{ __('wiki::messages.files.title') }}</h2>
                        <ul class="list-group">
                            @foreach ($files as $file)
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3"><a
                                        class="text-decoration-none"
                                        href="{{ route('plugins.wiki.public.files.download', $file->id) }}"><i class="bi bi-download me-2"
                                            aria-hidden="true"></i>{{ $file->name }}</a><small
                                        class="text-body-secondary">{{ __('wiki::messages.files.size', ['size' => number_format($file->size / 1024, 1)]) }}</small>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                <nav class="d-flex justify-content-between flex-wrap gap-3 border-top pt-3"
                    aria-label="{{ __('wiki::messages.editor.adjacent_pages') }}">
                    <div>
                        @if ($previous)
                            <a data-wiki-article-link href="{{ app(Documents::class)->url($previous) }}">
                                <span class="d-block small text-body-secondary">{{ __('wiki::messages.editor.previous') }}</span>
                                {{ $previous->title }}
                            </a>
                        @endif
                    </div>
                    <div class="text-end">
                        @if ($next)
                            <a data-wiki-article-link href="{{ app(Documents::class)->url($next) }}">
                                <span class="d-block small text-body-secondary">{{ __('wiki::messages.editor.next') }}</span>
                                {{ $next->title }}
                            </a>
                        @endif
                    </div>
                </nav>
            </article>
        </div>
    </div>
@endsection
@push('scripts')
    <script type="module" src="{{ route('site.extensions.assets.show', ['id' => 'wiki', 'asset' => 'navigation.js']) }}"></script>
@endpush
