@foreach ($nodes as $node)
    @php
        $category = $node['category'];
        $hasChildren = $node['children'] !== [];
        $editable = $categoryItems->has($category->id);
        $labelTag = $hasChildren ? 'summary' : 'div';
    @endphp
    <li class="position-relative py-2">
        @if ($hasChildren)
            <details @if (!$editable || $categoryOptions['search'] !== '') open @endif>
        @endif
            <{{ $labelTag }} @class(['text-break', 'ps-3' => !$hasChildren, 'pe-5' => $editable])>
                @if (class_exists(\App\Kraft\ContentIcons::class))
                    <x-dynamic-component component="content-icon" :name="$category->icon" class="me-1 text-body-secondary" />
                @else
                    <i class="bi bi-{{ $category->icon }} me-1 text-body-secondary" aria-hidden="true"></i>
                @endif
                <span @class(['fw-medium' => $editable, 'text-body-secondary' => !$editable])>{{ $category->name }}</span>
                <span class="small text-body-secondary ms-1">{{ strtoupper($category->locale) }} · {{ __('wiki::messages.common.position') }} {{ $category->position }}</span>
                @if (!$category->enabled)
                    <span class="badge text-bg-secondary">{{ __('wiki::messages.files.hidden') }}</span>
                @endif
                @if ($category->read_access !== 'public')
                    <span class="badge text-bg-light">{{ __('wiki::messages.files.' . $category->read_access) }}</span>
                @endif
            </{{ $labelTag }}>
        @if ($hasChildren)
                <ul class="list-unstyled border-start ms-1 ps-1 mt-1 mb-0">
                    @include('wiki::admin.categories.partials.tree', ['nodes' => $node['children']])
                </ul>
            </details>
        @endif
        @if ($editable)
            <div class="dropdown position-absolute top-0 end-0 mt-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                    aria-label="{{ __('common.labels.actions') }} · {{ $category->name }}" aria-expanded="false">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button class="dropdown-item" type="button" data-bs-toggle="modal"
                            data-bs-target="#wiki-category-{{ $category->id }}">{{ __('common.actions.edit') }}</button>
                    </li>
                    <li>
                        <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal"
                            data-bs-target="#wiki-category-delete-{{ $category->id }}">{{ __('common.actions.delete') }}</button>
                    </li>
                </ul>
            </div>
        @endif
    </li>
@endforeach
