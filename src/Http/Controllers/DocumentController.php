<?php

namespace KraftInk\Plugins\Wiki\Http\Controllers;

use App\Kraft\System\TableQuery;
use App\Kraft\Html;
use App\Kraft\LocalePolicy;
use App\Kraft\Site\Seo;
use App\Models\Accounts\Role;
use App\Models\Accounts\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Wiki\Models\Category;
use KraftInk\Plugins\Wiki\Models\CategoryRole;
use KraftInk\Plugins\Wiki\Models\Document;
use KraftInk\Plugins\Wiki\Models\DocumentRevision;
use KraftInk\Plugins\Wiki\Services\CategoryAccess;
use KraftInk\Plugins\Wiki\Services\DocumentImages;
use KraftInk\Plugins\Wiki\Services\Documents;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DocumentController
{
    public const string PLUGIN = 'wiki';

    public function index(Request $request, Documents $library): Response
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => [
                'nullable',
                'integer',
                Rule::exists('wiki__categories', 'id')->where('locale', app()->getLocale()),
            ],
        ]);
        $query = $library->visible($request->user());
        if ($request->filled('q')) {
            $query->where(
                fn ($query) => $query
                    ->whereLike('title', '%'.$data['q'].'%')
                    ->orWhereLike('body', '%'.$data['q'].'%'),
            );
        }
        $category = !empty($data['category']) ? Category::query()->find($data['category']) : null;
        if (
            $category &&
            !in_array((int) $category->id, app(CategoryAccess::class)->readableIds($request->user()), true)
        ) {
            abort(404);
        }
        if ($category) {
            $query->whereIn('category_id', $library->descendants($category->id));
        }
        $documents = $query->with('category')->orderBy('position')->orderBy('title')->paginate(30)->withQueryString();
        $canIndex = !$category || in_array((int) $category->id, app(CategoryAccess::class)->readableIds(null), true);

        return response()
            ->view('wiki::default.documents.index', [
                'documents' => $documents,
                'category' => $category,
                'navigation' => $library->navigation($request->user(), $category?->id),
                'plugin' => static::PLUGIN,
                'seo' => [
                    'excerpt' => $category?->description,
                    'canonical' => $category
                        ? route('plugins.wiki.public.documents.index', ['category' => $category->id])
                        : '',
                    'robots' => $canIndex ? '' : Seo::NO_INDEX,
                ],
                'seoVariables' => [
                    'category' => $category?->name ?? __('wiki::messages.common.wiki'),
                    'category_description' => $category?->description,
                    'documents_count' => $documents->total(),
                ],
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    private function render(Request $request, Document $document, Documents $library): Response
    {
        $siblings = $library
            ->visible($request->user())
            ->where('category_id', $document->category_id)
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'category_id']);
        $position = $siblings->search(fn ($item) => $item->id === $document->id);
        $alternates = $library->indexableAlternates($document);
        $canIndex = $alternates === null
            ? Document::query()->indexable()->whereKey($document->id)->exists()
            : isset($alternates[$document->locale]);
        $category = $document->category;

        return response()
            ->view('wiki::default.documents.show', [
                'document' => $document,
                'category' => $category,
                'navigation' => $library->navigation($request->user(), $document->category_id),
                'translations' => $library->translations($document, $request->user()),
                'previous' => $position > 0 ? $siblings[$position - 1] : null,
                'next' => $siblings[$position + 1] ?? null,
                'files' => $document->files()->orderBy('created_at')->get(['id', 'name', 'size']),
                'plugin' => static::PLUGIN,
                'seo' => [
                    'excerpt' => $document->body,
                    'seo_title' => $document->seo_title,
                    'seo_description' => $document->seo_description,
                    'canonical' => $library->url($document),
                    'robots' => $canIndex ? '' : Seo::NO_INDEX,
                    'alternates' => $alternates ?? [],
                ],
                'seoVariables' => [
                    'document' => $document->title,
                    'document_description' => $canIndex ? $document->body : '',
                    'category' => $category?->name,
                ],
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $locale, string $category, string $slug, Documents $library): Response
    {
        $category = filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($category === false) {
            abort(404);
        }
        $policy = app(LocalePolicy::class);
        if (!$policy->valid($locale)) {
            abort(404);
        }
        if (!($policy->allowsChoice() || $locale === $policy->defaultLocale())) {
            abort(404);
        }
        $document = $library
            ->visible($request->user(), $locale)
            ->where('slug', $slug)
            ->where('category_id', $category ?: null)
            ->first();
        if ($document === null) {
            abort(404);
        }
        $cookie = $policy->allowsChoice() ? $policy->choose($request, $locale) : null;
        app()->setLocale($locale);
        /** @var Document $document */
        $response = $this->render($request, $document, $library);

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    public function admin(Request $request, Documents $library): View
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['documents', 'categories'])],
            'q' => 'nullable|string|max:100',
            'locale' => ['nullable', Rule::in(array_keys(config('kraft.locales')))],
            'state' => ['nullable', Rule::in(['published', 'draft'])],
        ]);
        $tab = $data['tab'] ?? ($request->route('section') ?? 'documents');
        if (str_starts_with((string) $request->old('_modal'), 'wiki-category-')) {
            $tab = 'categories';
        }
        $query = Document::query()->whereNull('deleted_at');
        if ($request->filled('q')) {
            $query->whereLike('title', '%'.$data['q'].'%');
        }
        if (!empty($data['locale'])) {
            $query->where('locale', $data['locale']);
        }
        if (!empty($data['state'])) {
            $query->where('published', $data['state'] === 'published');
        }
        $categoryPage = TableQuery::paginate(
            Category::query()->orderBy('position')->orderBy('name')->orderBy('id'),
            ['name' => 'name', 'locale' => 'locale', 'position' => 'position'],
            pageName: 'categories_page',
            search: ['name'],
        )
            ->withQueryString()
            ->appends(['tab' => 'categories']);
        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id', 'locale', 'icon', 'enabled', 'read_access', 'position']);
        $categoryOptions = TableQuery::options($categoryPage);
        $categoryItems = $categoryPage->getCollection()->keyBy('id');

        return view('wiki::admin.documents.index', [
            'documents' => TableQuery::paginate(
                $query->orderBy('position')->orderBy('title')->orderBy('id'),
                ['title' => 'title', 'locale' => 'locale', 'published' => 'published'],
                pageName: 'documents_page',
                search: [],
            )
                ->withQueryString()
                ->appends(['tab' => 'documents']),
            'categoryPage' => $categoryPage,
            'categories' => $categories,
            'categoryOptions' => $categoryOptions,
            'categoryTree' => $library->tree(
                $this->categoryBranches($categories, $categoryItems, $categoryOptions),
            ),
            'categoryItems' => $categoryItems,
            'roles' => Role::query()
                ->orderByDesc('position')
                ->get(['id', 'name']),
            'categoryRoles' => CategoryRole::query()
                ->whereIn('category_id', $categoryPage->pluck('id'))
                ->get()
                ->groupBy('category_id')
                ->map(fn (Collection $rows) => $rows->pluck('role_id')->all()),
            'tab' => $tab,
            'plugin' => static::PLUGIN,
        ]);
    }

    private function categoryBranches(Collection $categories, Collection $page, array $options): Collection
    {
        $byId = $categories->keyBy('id');
        $visible = array_fill_keys($page->keys()->all(), true);
        foreach ($page as $category) {
            $parent = $category->parent_id;
            while ($parent !== null && !isset($visible[$parent]) && $byId->has($parent)) {
                $visible[$parent] = true;
                $parent = $byId[$parent]->parent_id;
            }
        }
        $branches = $categories->filter(fn (Category $category) => isset($visible[$category->id]));

        return $options['sort'] === ''
            ? $branches
            : $branches->sortBy([[$options['sort'], $options['direction']], ['id', 'asc']]);
    }

    public function edit(?int $id = null): View
    {
        $document = $id ? Document::query()->whereNull('deleted_at')->find($id) : null;
        if ($id && !$document) {
            abort(404);
        }
        $translations = $document?->translation_group
            ? Document::query()
                ->where('translation_group', $document->translation_group)
                ->where('id', '!=', $id)
                ->pluck('id')
                ->all()
            : [];

        return view('wiki::admin.documents.edit', [
            'document' => $document,
            'categories' => app(Documents::class)->categories(),
            'translations' => Document::query()
                ->where(fn ($query) => $query->whereNull('deleted_at')->orWhereIn('id', $translations))
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->orderBy('title')
                ->get(['id', 'title', 'locale', 'deleted_at']),
            'translationId' => $translations[0] ?? null,
            'files' => $document?->files()->orderBy('created_at')->get(['id', 'name', 'size']) ?? collect(),
            'plugin' => static::PLUGIN,
        ]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $document = $id ? Document::query()->whereNull('deleted_at')->find($id) : null;
        if ($id && !$document) {
            abort(404);
        }
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'max:180'],
            'body' => 'required|string|max:100000',
            'locale' => ['required', Rule::in(array_keys(config('kraft.locales')))],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('wiki__categories', 'id')->where('locale', $request->input('locale')),
            ],
            'position' => 'required|integer|min:0|max:100000',
            'permission' => 'nullable|regex:/^[a-zA-Z0-9_.-]+$/D|max:100',
            'published' => 'sometimes|boolean',
            'translation_id' => 'nullable|integer|exists:wiki__documents,id',
            'seo_title' => 'nullable|string|max:180',
            'seo_description' => 'nullable|string|max:320',
            'noindex' => 'sometimes|boolean',
        ]);
        $data['body'] = Html::clean($data['body']);
        $data['published'] = $request->boolean('published');
        $data['noindex'] = $request->has('noindex') ? $request->boolean('noindex') : $document?->noindex ?? false;
        $data['updated_at'] = now();
        DB::transaction(function () use ($data, $document, $id, $request) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($document) {
                $document = Document::query()->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            }
            if ($id && !$document) {
                abort(404);
            }
            if ($request->has('translation_id')) {
                $data['translation_group'] = app(Documents::class)->linkTranslation(
                    $id,
                    $data['translation_id'] ?? null,
                    $data['locale'],
                );
            } elseif (
                $document?->translation_group &&
                $document->locale !== $data['locale'] &&
                Document::query()
                    ->where('translation_group', $document->translation_group)
                    ->where('locale', $data['locale'])
                    ->where('id', '!=', $id)
                    ->exists()
            ) {
                throw ValidationException::withMessages(['locale' => __('wiki::messages.editor.translation_exists')]);
            }
            unset($data['translation_id']);
            if (
                !$request->has('category_id') &&
                $document?->category_id &&
                Category::query()->where('id', $document->category_id)->where('locale', '!=', $data['locale'])->exists()
            ) {
                $data['category_id'] = null;
            }
            app(Documents::class)->assertAvailableSlug(
                $data['locale'],
                $data['slug'],
                array_key_exists('category_id', $data) ? $data['category_id'] : $document?->category_id,
                $id,
            );
            if ($document) {
                $document->recordRevision($request->user()->id);
                Document::query()->where('id', $id)->update($data);
            } else {
                $id = Document::query()->insertGetId($data + ['created_at' => now()]);
            }
            app(DocumentImages::class)->attach($id, $data['body'], $request->user());
        }, 3);

        return redirect()
            ->route('plugins.wiki.admin.documents.index')
            ->with('status', __('wiki::messages.common.saved'));
    }

    public function saveCategory(Request $request, ?int $id = null): RedirectResponse
    {
        if ($id && !Category::query()->where('id', $id)->exists()) {
            abort(404);
        }
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('wiki__categories')
                    ->where('locale', $request->input('locale'))
                    ->where('parent_id', $request->filled('parent_id') ? $request->integer('parent_id') : null)
                    ->ignore($id),
            ],
            'locale' => ['required', Rule::in(array_keys(config('kraft.locales')))],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('wiki__categories', 'id')->where('locale', $request->input('locale')),
            ],
            'description' => 'nullable|string|max:1000',
            'position' => 'required|integer|min:0|max:100000',
        ]);
        $access = $request->validate([
            'enabled' => 'sometimes|boolean',
            'read_access' => ['sometimes', Rule::in(['public', 'members', 'roles'])],
            'permission' => 'nullable|regex:/^[a-zA-Z0-9_.-]+$/D|max:100',
            'icon' => ['sometimes', 'required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/D'],
            'roles' => 'nullable|array|max:100',
            'roles.*' => 'required|integer|distinct|exists:roles,id',
            'roles_present' => 'sometimes|boolean',
        ]);
        $roles =
            $request->has('roles') || $request->boolean('roles_present')
                ? $access['roles'] ?? []
                : ($id
                    ? CategoryRole::query()->where('category_id', $id)->pluck('role_id')->all()
                    : []);
        unset($access['roles'], $access['roles_present']);
        $existingAccess = $id ? Category::query()->where('id', $id)->value('read_access') : 'public';
        if (($access['read_access'] ?? $existingAccess) === 'roles' && !$roles) {
            throw ValidationException::withMessages(['roles' => __('wiki::messages.files.roles_required')]);
        }
        $data += $access;
        DB::transaction(function () use ($data, $id, $roles) {
            $categories = Category::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'parent_id'])
                ->keyBy('id');
            $parent = $data['parent_id'] ?? null;
            $seen = $id ? [$id] : [];
            for ($depth = 0; $parent; $depth++) {
                if ($depth >= 19 || in_array((int) $parent, $seen, true)) {
                    throw ValidationException::withMessages([
                        'parent_id' => __('wiki::messages.editor.invalid_parent'),
                    ]);
                }
                $seen[] = (int) $parent;
                $parent = $categories[$parent]->parent_id ?? null;
            }
            if ($id) {
                $categories[$id]->parent_id = $data['parent_id'] ?? null;
                $descendants = [$id];
                for ($depth = count($seen); $descendants; $depth++) {
                    if ($depth > 20) {
                        throw ValidationException::withMessages([
                            'parent_id' => __('wiki::messages.editor.invalid_parent'),
                        ]);
                    }
                    $descendants = $categories->whereIn('parent_id', $descendants)->pluck('id')->all();
                }
            }
            if (
                $id &&
                (Document::query()->where('category_id', $id)->where('locale', '!=', $data['locale'])->exists() ||
                    Category::query()->where('parent_id', $id)->where('locale', '!=', $data['locale'])->exists())
            ) {
                throw ValidationException::withMessages([
                    'locale' => __('wiki::messages.editor.category_language_in_use'),
                ]);
            }
            $data['updated_at'] = now();
            if ($id) {
                Category::query()->where('id', $id)->update($data);
            } else {
                $id = Category::query()->insertGetId($data + ['created_at' => now()]);
            }
            Category::query()->findOrFail($id)->roles()->sync($roles);
        }, 3);

        return redirect()
            ->route('plugins.wiki.admin.categories.index')
            ->with('status', __('wiki::messages.common.saved'));
    }

    public function destroyCategory(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            if (Category::query()->where('id', $id)->lockForUpdate()->first() === null) {
                abort(404);
            }
            if (
                !in_array($id, app(CategoryAccess::class)->readableIds(null), true) &&
                (Document::query()->where('category_id', $id)->exists() ||
                    Category::query()->where('parent_id', $id)->exists())
            ) {
                throw ValidationException::withMessages(['category' => __('wiki::messages.files.protected_category')]);
            }
            foreach (
                Document::query()
                    ->where('category_id', $id)
                    ->get(['id', 'locale', 'slug']) as $document
            ) {
                /** @var Document $document */
                app(Documents::class)->assertAvailableSlug($document->locale, $document->slug, null, $document->id);
            }
            Document::query()
                ->where('category_id', $id)
                ->update(['category_id' => null]);
            Category::query()
                ->where('parent_id', $id)
                ->update(['parent_id' => null]);
            Category::query()->where('id', $id)->delete();
        }, 3);

        return redirect()
            ->route('plugins.wiki.admin.categories.index')
            ->with('status', __('wiki::messages.common.deleted'));
    }

    public function revisions(int $id): View
    {
        $document = Document::query()->whereNull('deleted_at')->find($id);
        if ($document === null) {
            abort(404);
        }

        return view('wiki::admin.documents.revisions', [
            'document' => $document,
            'revisions' => TableQuery::paginate(
                DocumentRevision::query()->where('document_id', $id)->orderByDesc('id'),
                ['created_at' => 'created_at'],
                pageName: 'page',
                search: [],
            ),
            'plugin' => static::PLUGIN,
        ]);
    }

    public function restore(Request $request, int $id, int $revision): RedirectResponse
    {
        DB::transaction(function () use ($request, $id, $revision) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $document = Document::query()->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            $record = $document?->revisions()->find($revision);
            if (!($document && $record)) {
                abort(404);
            }
            $snapshot = $record->snapshot;
            if (!empty($snapshot['anonymized'])) {
                throw new HttpException(410, __('wiki::messages.privacy.revision_removed'));
            }
            $data = array_intersect_key(
                $snapshot,
                array_flip([
                    'title',
                    'body',
                    'category_id',
                    'position',
                    'permission',
                    'published',
                    'seo_title',
                    'seo_description',
                    'noindex',
                ]),
            );
            if (
                !empty($data['category_id']) &&
                !Category::query()->where('id', $data['category_id'])->where('locale', $document->locale)->exists()
            ) {
                $data['category_id'] = null;
            }
            app(Documents::class)->assertAvailableSlug(
                $document->locale,
                $document->slug,
                $data['category_id'] ?? null,
                $id,
            );
            $data['body'] = Html::clean($data['body'] ?? '');
            $data['updated_at'] = now();
            $document->recordRevision($request->user()->id);
            Document::query()->where('id', $id)->update($data);
            app(DocumentImages::class)->attach($id, $data['body'], $request->user());
        }, 3);

        return redirect()
            ->route('plugins.wiki.admin.documents.edit', $id)
            ->with('status', __('wiki::messages.common.restored'));
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            $document = Document::query()->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            if ($document === null) {
                abort(404);
            }
            Document::query()
                ->where('id', $id)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
        }, 3);

        return redirect()
            ->route('plugins.wiki.admin.documents.index')
            ->with('status', __('site.content.moved_to_trash'));
    }
}
