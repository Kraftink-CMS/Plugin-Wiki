@extends('admin.layouts.layout')
@use('App\Kraft\System\TableQuery')
@section('title', __('wiki::messages.common.wiki'))
@section('content')
    <div data-hash-tabs data-hash-tabs-errors="{{ json_encode($errors->keys()) }}">
        <ul class="nav nav-tabs mb-3" role="tablist">
            @foreach (['documents', 'categories'] as $panel)
                <li class="nav-item" role="presentation">
                    <button @class(['nav-link', 'active' => $tab === $panel]) id="wiki-tab-{{ $panel }}" type="button" data-bs-toggle="tab"
                        data-bs-target="#wiki-library-{{ $panel }}" role="tab"
                        aria-controls="wiki-library-{{ $panel }}"
                        aria-selected="{{ $tab === $panel ? 'true' : 'false' }}">{{ __('wiki::messages.editor.' . $panel) }}</button>
                </li>
            @endforeach
        </ul>
        <div class="tab-content">
            <div @class(['tab-pane fade', 'show active' => $tab === 'documents']) id="wiki-library-documents" role="tabpanel"
                aria-labelledby="wiki-tab-documents">
                <div id="wiki-admin-documents" data-ajax-pagination data-table tabindex="-1" class="vstack gap-4">
                    <div class="mb-3"><a class="btn btn-primary"
                            href="{{ route('plugins.wiki.admin.documents.create') }}">{{ __('wiki::messages.common.create') }}</a></div>
                    <section class="card">
                        <div class="card-body">
                            <x-tables.controls :paginator="$documents" class="mb-3" />
                            <form class="row g-2" method="get" action="{{ route('plugins.wiki.admin.documents.index') }}">
                                <input type="hidden" name="tab" value="documents">
                                <div class="col-md-5">
                                    <input class="form-control" name="q" value="{{ request('q') }}"
                                        placeholder="{{ __('wiki::messages.common.search') }}"
                                        aria-label="{{ __('wiki::messages.common.search') }}" maxlength="100">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="locale"
                                        aria-label="{{ __('wiki::messages.common.language') }}">
                                        <option value="">{{ __('wiki::messages.editor.all_languages') }}</option>
                                        @foreach (config('kraft.locales') as $code => $label)
                                            <option value="{{ $code }}" @selected(request('locale') === $code)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="state" aria-label="{{ __('wiki::messages.editor.state') }}">
                                        <option value="">{{ __('wiki::messages.editor.all_states') }}</option>
                                        @foreach (['published', 'draft'] as $state)
                                            <option value="{{ $state }}" @selected(request('state') === $state)>
                                                {{ __('wiki::messages.editor.' . $state) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <noscript>
                                    <div class="col-md-1">
                                        <button class="btn btn-outline-secondary w-100" type="submit"
                                            aria-label="{{ __('wiki::messages.common.search') }}"><i class="bi bi-search"
                                                aria-hidden="true"></i></button>
                                    </div>
                                </noscript>
                            </form>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="text-nowrap">
                                    <tr>
                                        <th scope="col" data-table-sort="title">{{ __('wiki::messages.common.title') }}</th>
                                        <th>{{ __('wiki::messages.common.category') }}</th>
                                        <th scope="col" data-table-sort="locale">{{ __('wiki::messages.common.language') }}</th>
                                        <th scope="col" data-table-sort="published">{{ __('wiki::messages.editor.state') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $item)
                                        <tr>
                                            <td><strong>{{ $item->title }}</strong>
                                                @if ($item->permission)
                                                    <span
                                                        class="badge text-bg-secondary ms-1">{{ __('wiki::messages.editor.restricted') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $categories->firstWhere('id', $item->category_id)?->name ?? '—' }}
                                            </td>
                                            <td>{{ strtoupper($item->locale) }}</td>
                                            <td><span
                                                    @class([
                                                        'badge',
                                                        'text-bg-success' => $item->published,
                                                        'text-bg-secondary' => !$item->published,
                                                    ])>{{ __('wiki::messages.editor.' . ($item->published ? 'published' : 'draft')) }}</span>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                        type="button"
                                                        data-bs-toggle="dropdown">{{ __('common.labels.actions') }}</button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item"
                                                                href="{{ route('plugins.wiki.admin.documents.edit', $item->id) }}">{{ __('common.actions.edit') }}</a>
                                                        </li>
                                                        <li><a class="dropdown-item"
                                                                href="{{ route('plugins.wiki.admin.documents.revisions.index', $item->id) }}">{{ __('wiki::messages.common.revisions') }}</a>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" type="button"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#wiki-document-delete-{{ $item->id }}">{{ __('common.actions.delete') }}</button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <x-tables.empty :colspan="5" :message="__('wiki::messages.editor.no_documents')" :action="__('wiki::messages.common.create')"
                                            :href="route('plugins.wiki.admin.documents.create')" />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            {{ $documents->links() }}
                        </div>
                    </section>
                    @foreach ($documents as $item)
                        <x-forms.modal :id="'wiki-document-delete-' . $item->id" :title="__('common.actions.delete')" :action="route('plugins.wiki.admin.documents.destroy', $item->id)" method="DELETE" :submit="__('common.actions.delete')"
                            size="sm">
                            <p>{{ __('wiki::messages.editor.delete_document') }}</p>
                            <strong>{{ $item->title }}</strong>
                        </x-forms.modal>
                    @endforeach
                </div>
            </div>
            <div @class(['tab-pane fade', 'show active' => $tab === 'categories']) id="wiki-library-categories" role="tabpanel"
                aria-labelledby="wiki-tab-categories">
                <div id="wiki-admin-categories" data-ajax-pagination data-table tabindex="-1" class="vstack gap-4">
                    <div class="mb-3"><button class="btn btn-primary" type="button" data-bs-toggle="modal"
                            data-bs-target="#wiki-category-new">{{ __('wiki::messages.editor.add_category') }}</button></div>
                    <section class="card">
                        <div class="card-body d-flex flex-wrap align-items-end gap-3">
                            <form method="get" action="{{ request()->url() }}" class="flex-grow-1" data-table-filters>
                                @foreach (TableQuery::fields(request()->query(), ['tab', 'categories_page', 'categories_page_search']) as $field)
                                    <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
                                @endforeach
                                <input type="hidden" name="tab" value="categories" data-table-parameter="tab">
                                <label class="form-label" for="wiki-category-search">{{ __('wiki::messages.common.search') }}</label>
                                <input class="form-control" id="wiki-category-search" type="search" name="categories_page_search"
                                    data-table-parameter="categories_page_search" value="{{ $categoryOptions['search'] }}"
                                    maxlength="100" autocomplete="off">
                                <noscript>
                                    <button class="btn btn-outline-secondary mt-2" type="submit">{{ __('tables.apply') }}</button>
                                </noscript>
                            </form>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (['name' => __('wiki::messages.editor.category_name'), 'locale' => __('wiki::messages.common.language'), 'position' => __('wiki::messages.common.position')] as $sort => $label)
                                    @php
                                        $activeSort = $categoryOptions['sort'] === $sort;
                                        $direction = $activeSort && $categoryOptions['direction'] === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a @class(['btn btn-outline-secondary', 'active' => $activeSort]) data-table-order="{{ $sort }}"
                                        data-table-direction="{{ $direction }}"
                                        href="{{ request()->fullUrlWithQuery(['tab' => 'categories', 'categories_page' => 1, 'categories_page_sort' => $sort, 'categories_page_direction' => $direction]) }}"
                                        aria-label="{{ __($direction === 'asc' ? 'tables.sort_ascending' : 'tables.sort_descending', ['column' => $label]) }}">
                                        {{ $label }}
                                        @if ($activeSort)
                                            <i @class(['bi', 'bi-arrow-up' => $direction === 'desc', 'bi-arrow-down' => $direction === 'asc']) aria-hidden="true"></i>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            @if ($categoryTree)
                                <ul class="list-unstyled mb-0">
                                    @include('wiki::admin.categories.partials.tree', ['nodes' => $categoryTree])
                                </ul>
                            @else
                                <p class="text-body-secondary mb-0">{{ __('wiki::messages.editor.no_categories') }}</p>
                            @endif
                        </div>
                        <div class="card-footer">
                            {{ $categoryPage->links() }}
                        </div>
                    </section>
                    <x-forms.modal id="wiki-category-new" :title="__('wiki::messages.editor.add_category')" :action="route('plugins.wiki.admin.categories.store')">
                        @include('wiki::admin.categories.partials.fields', ['editingCategory' => null])
                    </x-forms.modal>
                    @foreach ($categoryPage as $item)
                        <x-forms.modal :id="'wiki-category-' . $item->id" :title="__('wiki::messages.editor.edit_category')" :action="route('plugins.wiki.admin.categories.update', $item->id)" method="PUT">
                            @include('wiki::admin.categories.partials.fields', ['editingCategory' => $item])
                        </x-forms.modal>
                        <x-forms.modal :id="'wiki-category-delete-' . $item->id" :title="__('common.actions.delete')" :action="route('plugins.wiki.admin.categories.destroy', $item->id)" method="DELETE"
                            :submit="__('common.actions.delete')" size="sm">
                            <p>{{ __('wiki::messages.editor.delete_category') }}</p>
                            <strong>{{ $item->name }}</strong>
                        </x-forms.modal>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
