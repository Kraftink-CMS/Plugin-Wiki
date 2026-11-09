<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Kraft\LocalePolicy;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Wiki\Models\Category;
use KraftInk\Plugins\Wiki\Models\Document;

class Documents
{
    public function url(object $document): string
    {
        return route('plugins.wiki.public.documents.show', [
            'locale' => $document->locale,
            'category' => (int) $document->category_id,
            'slug' => $document->slug,
        ]);
    }

    public function assertAvailableSlug(string $locale, string $slug, ?int $category, ?int $except = null): void
    {
        if (
            Document::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where('category_id', $category)
                ->when($except, fn ($query) => $query->where('id', '!=', $except))
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => __('wiki::messages.editor.address')]),
            ]);
        }
    }

    public function visible(?User $user, ?string $locale = null): Builder
    {
        return Document::query()->visibleTo($user, $locale);
    }

    public function readable(?User $user): Builder
    {
        return Document::query()->readableBy($user);
    }

    public function categories(?string $locale = null): Collection
    {
        return Category::query()
            ->when($locale, fn ($query) => $query->where('locale', $locale))
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function descendants(int $id): array
    {
        return Category::descendantIds($id);
    }

    public function tree(Collection $categories): array
    {
        $children = $categories->groupBy(fn ($category) => (int) $category->parent_id);
        $walk = function (int $parent, array $seen = []) use (&$walk, $children): array {
            if (count($seen) >= 20) {
                return [];
            }

            return $children
                ->get($parent, collect())
                ->filter(fn ($category) => !in_array($category->id, $seen, true))
                ->map(
                    fn ($category) => [
                        'category' => $category,
                        'children' => $walk($category->id, [...$seen, $category->id]),
                    ],
                )
                ->all();
        };

        return $walk(0);
    }

    public function navigation(?User $user, ?int $activeCategory = null): array
    {
        $documents = $this->visible($user)
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'category_id', 'locale']);
        $categories = $this->categories(app()->getLocale());
        $needed = $documents->pluck('category_id')->filter()->mapWithKeys(fn ($id) => [$id => true])->all();
        $byId = $categories->keyBy('id');
        $expanded = [];
        for ($depth = 0; $activeCategory && $depth < 20; $depth++) {
            $expanded[] = $activeCategory;
            $activeCategory = $byId[$activeCategory]->parent_id ?? null;
        }
        foreach (array_keys($needed) as $id) {
            for ($depth = 0; $depth < 20 && ($parent = $byId[$id]->parent_id ?? null); $depth++, $id = $parent) {
                $needed[$parent] = true;
            }
        }

        return [
            'tree' => $this->tree($categories->filter(fn ($category) => isset($needed[$category->id]))),
            'documents' => $documents->groupBy(fn ($document) => (int) $document->category_id),
            'expanded' => $expanded,
        ];
    }

    public function translations(Document $document, ?User $user): Collection
    {
        return $this->translationQuery($document)
            ?->readableBy($user)
            ->get(['id', 'locale', 'title', 'slug', 'category_id']) ?? collect();
    }

    public function indexableAlternates(Document $document): ?array
    {
        return $this->translationQuery($document)
            ?->indexable()
            ->get(['locale', 'slug', 'category_id'])
            ->mapWithKeys(fn (Document $translation) => [$translation->locale => $this->url($translation)])
            ->all();
    }

    private function translationQuery(Document $document): ?Builder
    {
        if (!$document->translation_group || !app(LocalePolicy::class)->allowsChoice()) {
            return null;
        }

        return Document::query()
            ->whereIn('locale', array_keys(config('kraft.locales')))
            ->where('translation_group', $document->translation_group)
            ->orderBy('locale');
    }

    public function linkTranslation(?int $documentId, ?int $targetId, string $locale): ?string
    {
        if (!$targetId) {
            return null;
        }
        $target = Document::query()->where('id', $targetId)->lockForUpdate()->first();
        $existingGroup =
            $documentId &&
            $target?->translation_group &&
            Document::query()
                ->where('id', $documentId)
                ->where('translation_group', $target->translation_group)
                ->exists();
        if (
            !$target ||
            ($target->deleted_at && !$existingGroup) ||
            $target->id === $documentId ||
            $target->locale === $locale
        ) {
            throw ValidationException::withMessages([
                'translation_id' => __('wiki::messages.editor.invalid_translation'),
            ]);
        }
        $group = $target->translation_group ?: (string) Str::uuid();
        if (
            Document::query()
                ->where('translation_group', $group)
                ->where('locale', $locale)
                ->when($documentId, fn ($query) => $query->where('id', '!=', $documentId))
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'translation_id' => __('wiki::messages.editor.translation_exists'),
            ]);
        }
        Document::query()
            ->where('id', $targetId)
            ->update(['translation_group' => $group]);

        return $group;
    }
}
