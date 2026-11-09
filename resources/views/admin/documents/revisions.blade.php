@use('App\Kraft\Html')
@use('App\Kraft\SiteDates')
@extends('admin.layouts.layout')
@section('title', __('wiki::messages.common.revisions'))
@section('actions')
    <a class="btn btn-outline-secondary" href="{{ route('plugins.wiki.admin.documents.edit', $document->id) }}">
        {{ __('wiki::messages.common.edit') }}</a>
@endsection
@section('content')
    <div id="wiki-admin-revisions" data-ajax-pagination data-table tabindex="-1" class="vstack gap-4">
        <x-admin::tables.paginated :paginator="$revisions" :title="$document->title">
            <table class="table align-middle mb-0">
                <thead class="text-nowrap">
                    <tr>
                        <th scope="col" data-table-sort="created_at">{{ __('wiki::messages.editor.version_date') }}</th>
                        <th>{{ __('wiki::messages.common.title') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revisions as $revision)
                        @php
                            $snapshot = $revision->snapshot;
                        @endphp
                        <tr>
                            <td>{{ app(SiteDates::class)->format($revision->created_at) }}</td>
                            <td>{{ !empty($snapshot['anonymized']) ? __('wiki::messages.privacy.revision_removed') : $snapshot['title'] ?? '' }}
                            </td>
                            <td class="text-end">
                                @if (empty($snapshot['anonymized']))
                                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal"
                                        data-bs-target="#wiki-restore-{{ $revision->id }}">{{ __('wiki::messages.editor.review_version') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-tables.empty :colspan="3" :message="__('wiki::messages.common.no_revisions')" />
                    @endforelse
                </tbody>
            </table>
        </x-admin::tables.paginated>
        @foreach ($revisions as $revision)
            @php
                $snapshot = $revision->snapshot;
            @endphp
            @if (empty($snapshot['anonymized']))
                <x-forms.modal :id="'wiki-restore-' . $revision->id" :title="__('wiki::messages.editor.review_version')" :action="route('plugins.wiki.admin.documents.revisions.restore', [$document->id, $revision->id])" :submit="__('wiki::messages.common.restore')" size="xl">
                    <p class="text-body-secondary">{{ __('wiki::messages.editor.restore_help') }}</p>
                    <h3 class="h5">{{ $snapshot['title'] ?? '' }}</h3>
                    <div class="prose">
                        {!! Html::clean($snapshot['body'] ?? '') !!}
                    </div>
                </x-forms.modal>
            @endif
        @endforeach
    </div>
@endsection
