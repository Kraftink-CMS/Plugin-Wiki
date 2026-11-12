<?php

namespace KraftInk\Plugins\Wiki\Services\Imports;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\ImportedHtml;
use Illuminate\Support\Str;
use KraftInk\Plugins\Wiki\Models\Category;
use KraftInk\Plugins\Wiki\Models\Document;

final class Writer
{
    public function document(ImportContext $context, int|string $identity, array $document): array
    {
        $slug = $this->importSlug(
            $document['slug'],
            config('imports.locale'),
            $document['category_id'],
            $context,
            $identity,
        );
        $id = $context->insert('wiki__documents', [
            'locale' => config('imports.locale'),
            'slug' => $slug,
            'title' => mb_substr(strip_tags($document['title']), 0, 180),
            'body' => ImportedHtml::clean($document['body']),
            'category_id' => $document['category_id'],
            'position' => max(0, (int) $document['position']),
            'published' => false,
            'created_at' => $document['created_at'],
            'updated_at' => now(),
        ]);

        return ['target_table' => 'wiki__documents', 'target_id' => $id];
    }

    public function category(ImportContext $context, int|string $identity, array $category, array $roles): int
    {
        $name = mb_substr(strip_tags($category['name']), 0, 120);
        if (
            $context->target
                ->table('wiki__categories')
                ->where('locale', config('imports.locale'))
                ->where('parent_id', $category['parent_id'])
                ->where('name', $name)
                ->exists()
        ) {
            $name =
                mb_substr($name, 0, 92).' ['.substr(hash('sha256', $context->runId.':'.$identity), 0, 16).']';
        }
        $icon = $category['icon'];
        $id = $context->insert('wiki__categories', [
            'locale' => config('imports.locale'),
            'name' => $name,
            'parent_id' => $category['parent_id'],
            'description' => strip_tags($category['description']),
            'position' => max(0, (int) $category['position']),
            'enabled' => (bool) $category['enabled'],
            'read_access' => $category['read_access'],
            'permission' => $category['permission'],
            'icon' => preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/D', $icon) ? $icon : 'book',
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at'],
        ]);
        foreach ($roles as $role) {
            $context->target->table('wiki__category_roles')->insertOrIgnore(['category_id' => $id, 'role_id' => $role]);
        }

        return $id;
    }

    public function replacements(): array
    {
        return [
            'wiki__files' => [
                'document_id' => Document::query()->where('locale', config('imports.locale'))->pluck('id')->all(),
            ],
            'wiki__document_images' => [
                'resource_id' => Document::query()->where('locale', config('imports.locale'))->pluck('id')->all(),
            ],
            'wiki__document_revisions' => [
                'document_id' => Document::query()->where('locale', config('imports.locale'))->pluck('id')->all(),
            ],
            'wiki__documents' => ['locale' => [config('imports.locale')]],
            'wiki__category_roles' => [
                'category_id' => Category::query()->where('locale', config('imports.locale'))->pluck('id')->all(),
            ],
            'wiki__categories' => ['locale' => [config('imports.locale')]],
        ];
    }

    public function importSlug(
        string $slug,
        string $locale,
        ?int $category,
        ImportContext $context,
        int|string $sourceId,
    ): string {
        $slug = substr(Str::slug($slug) ?: 'document', 0, 180);
        $exists = fn (string $candidate) => $context->target
            ->table('wiki__documents')
            ->where('locale', $locale)
            ->where('category_id', $category)
            ->where('slug', $candidate)
            ->exists();
        if (!$exists($slug)) {
            return $slug;
        }
        $suffix = '-import-'.substr(hash('sha256', $context->runId.':'.$sourceId), 0, 16);
        $candidate = substr($slug, 0, 180 - strlen($suffix)).$suffix;
        for ($attempt = 1; $exists($candidate); $attempt++) {
            $ending = $suffix.'-'.$attempt;
            $candidate = substr($slug, 0, 180 - strlen($ending)).$ending;
        }

        return $candidate;
    }
}
