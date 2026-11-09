@use('KraftInk\Plugins\Wiki\Models\Category')

@php
    $categoryModalId = $editingCategory ? 'wiki-category-' . $editingCategory->id : 'wiki-category-new';
@endphp
@php
    $categoryOld = old('_modal') === $categoryModalId;
@endphp
@php
    $categoryValue = fn($field, $fallback = null) => $categoryOld
        ? old($field, $editingCategory?->{$field} ?? $fallback)
        : $editingCategory?->{$field} ?? $fallback;
    $parentOptions = $editingCategory ? $categories->whereNotIn('id', Category::descendantIds($editingCategory->id, $categories)) : $categories;
@endphp
<div>
    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <label class="form-label" for="{{ $categoryModalId }}-name">{{ __('wiki::messages.editor.category_name') }}</label>
            <input class="form-control" id="{{ $categoryModalId }}-name" name="name" value="{{ $categoryValue('name') }}"
                maxlength="120" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="{{ $categoryModalId }}-locale">{{ __('wiki::messages.common.language') }}</label>
            <select class="form-select" id="{{ $categoryModalId }}-locale" name="locale">
                @foreach (config('kraft.locales') as $code => $label)
                    <option value="{{ $code }}" @selected($categoryValue('locale', app()->getLocale()) === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="{{ $categoryModalId }}-parent">{{ __('wiki::messages.editor.parent') }}</label>
        <select class="form-select" id="{{ $categoryModalId }}-parent" name="parent_id"
            data-option-filter="locale">
            <option value="">{{ __('wiki::messages.editor.no_parent') }}</option>
            @foreach ($parentOptions as $parent)
                <option value="{{ $parent->id }}"
                    data-option-group="{{ $parent->locale }}" @selected((int) $categoryValue('parent_id') === $parent->id)>{{ $parent->name }} ·
                    {{ strtoupper($parent->locale) }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label"
            for="{{ $categoryModalId }}-description">{{ __('wiki::messages.editor.category_description') }}</label>
        <textarea class="form-control" id="{{ $categoryModalId }}-description" name="description" rows="3"
            maxlength="1000">{{ $categoryValue('description') }}</textarea>
    </div>
    <div>
        <label class="form-label" for="{{ $categoryModalId }}-position">{{ __('wiki::messages.common.position') }}</label>
        <input class="form-control" id="{{ $categoryModalId }}-position" name="position" type="number" min="0"
            max="100000" value="{{ $categoryValue('position', 0) }}" required>
    </div>
</div>
<div class="mt-3">
    <label class="form-label" for="{{ $categoryModalId }}-icon">{{ __('wiki::messages.files.icon') }}</label>
    <select class="form-select" id="{{ $categoryModalId }}-icon" name="icon">
        @foreach (array_unique([$categoryValue('icon', 'book'), 'book', 'journal-text', 'info-circle', 'question-circle', 'shield-lock', 'file-earmark-text', 'map', 'tools', 'controller', 'people', 'star', 'gear']) as $icon)
            <option value="{{ $icon }}" @selected($categoryValue('icon', 'book') === $icon)>{{ $icon }}</option>
        @endforeach
    </select>
</div>
<input type="hidden" name="enabled" value="0">
<div class="form-check my-3">
    <input class="form-check-input" id="{{ $categoryModalId }}-enabled" type="checkbox" name="enabled" value="1"
        @checked($categoryValue('enabled', true))>
    <label class="form-check-label" for="{{ $categoryModalId }}-enabled">{{ __('wiki::messages.files.enabled') }}</label>
</div>
<div class="mb-3">
    <label class="form-label" for="{{ $categoryModalId }}-access">{{ __('wiki::messages.files.access') }}</label>
    <select class="form-select" name="read_access" id="{{ $categoryModalId }}-access">
        @foreach (['public', 'members', 'roles'] as $access)
            <option value="{{ $access }}" @selected($categoryValue('read_access', 'public') === $access)>{{ __('wiki::messages.files.' . $access) }}
            </option>
        @endforeach
    </select>
    <div class="form-text">
        {{ __('wiki::messages.files.access_help') }}
    </div>
</div>
@php
    $selectedCategoryRoles = $categoryOld ? old('roles', []) : $categoryRoles[$editingCategory?->id] ?? [];
@endphp
<input type="hidden" name="roles_present" value="1">
<fieldset class="mb-3">
    <legend class="fs-6">{{ __('wiki::messages.files.roles') }}</legend>
    @foreach ($roles as $role)
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}"
                @checked(in_array($role->id, $selectedCategoryRoles))>
            <span class="form-check-label">{{ $role->name }}</span></label>
    @endforeach
</fieldset>
<div>
    <label class="form-label" for="{{ $categoryModalId }}-permission">{{ __('wiki::messages.files.permission') }}</label>
    <input class="form-control" id="{{ $categoryModalId }}-permission" name="permission" maxlength="100"
        pattern="[A-Za-z0-9_.-]+" value="{{ $categoryValue('permission') }}">
</div>
