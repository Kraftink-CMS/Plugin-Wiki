<?php

namespace KraftInk\Plugins\Wiki\Services\Imports\Azuriom;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
use App\Kraft\Redirects;
use App\Models\Site\SiteRedirect;
use KraftInk\Plugins\Wiki\Services\Imports\Writer;
use RuntimeException;

final class Mappings
{
    private array $categoryRows = [];

    public function __construct(private readonly Writer $writer)
    {
    }

    public function register(MappingRegistry $registry): void
    {
        $registry->register('azuriom', 'wiki_categories', $this->category(...), 45, 'wiki');
        $registry->register(
            'azuriom',
            'wiki_pages',
            $this->document(...),
            50,
            'wiki',
            $this->writer->replacements(...),
            dependencies: ['wiki_categories'],
        );
    }

    private function document(array $row, ImportContext $context): array
    {
        $sourceCategoryId = $row['category_id'] ?? null;
        $categoryId = $sourceCategoryId ? $context->required('wiki_categories', $sourceCategoryId) : null;
        $result = $this->writer->document($context, $row['id'], [
            'slug' => $row['slug'] ?? $row['title'],
            'title' => $row['title'],
            'body' => $row['content'],
            'category_id' => $categoryId,
            'position' => $row['position'] ?? ($row['order'] ?? 0),
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ]);

        $metadata = [];
        if ($categoryId !== null && !empty($row['slug'])) {
            $categorySlug = $context->metadata('wiki_categories', $sourceCategoryId)['slug'] ?? null;
            if ($categorySlug === null) {
                foreach ($context->sourceRows('wiki_categories') ?? [] as $sourceRow) {
                    if ((string) $sourceRow['id'] === (string) $sourceCategoryId) {
                        $categorySlug = $sourceRow['slug'] ?? null;
                        break;
                    }
                }
            }
            if (
                is_string($categorySlug) &&
                preg_match('~^[^/?#\\x00-\\x20\\x7F]+$~uD', $categorySlug) &&
                preg_match('~^[^/?#\\x00-\\x20\\x7F]+$~uD', (string) $row['slug'])
            ) {
                $legacy = '/wiki/'.$categorySlug.'/'.$row['slug'];
                $slug = $context->target->table('wiki__documents')->where('id', $result['target_id'])->value('slug');
                $destination =
                    '/wiki/'.
                    rawurlencode(config('imports.locale')).
                    '/categories/'.
                    $categoryId.
                    '/pages/'.
                    rawurlencode($slug);
                $metadata = [
                    'legacy_path' => $legacy,
                    'legacy_redirect' => !SiteRedirect::on($context->target->getName())->where('source', $legacy)->exists() &&
                        app(Redirects::class)->preserve($legacy, $destination),
                ];
                if (!$metadata['legacy_redirect']) {
                    $context->report('review', [
                        'plugin' => 'wiki',
                        'table' => 'wiki_pages',
                        'id' => $row['id'],
                        'reason' => 'redirect_conflict',
                    ]);
                }
            }
        }
        if (!empty($row['miniature'])) {
            $context->report('media', [
                'plugin' => 'wiki',
                'table' => 'wiki_pages',
                'id' => $row['id'],
                'field' => 'miniature',
                'path' => $row['miniature'],
            ]);
        }

        return $result + ['metadata' => $metadata];
    }

    private function category(array $row, ImportContext $context, array $seen = []): array
    {
        $key = $context->runId;
        if ($mapped = $context->mapped('wiki_categories', $row['id'])) {
            return [
                'target_table' => 'wiki__categories',
                'target_id' => $mapped,
                'metadata' => ['name' => $row['name'], 'slug' => $row['slug'] ?? null],
            ];
        }
        if (in_array((string) $row['id'], $seen, true) || count($seen) >= 20) {
            throw new RuntimeException('Invalid imported category hierarchy.');
        }
        if (!isset($this->categoryRows[$key])) {
            $this->categoryRows[$key] = [];
            foreach ($context->sourceRows('wiki_categories') ?? [] as $item) {
                $this->categoryRows[$key][(string) $item['id']] = $item;
            }
        }
        $parentId = null;
        $parentSourceId = $row['parent_id'] ?? null;
        if ($parentSourceId) {
            $parent = $this->categoryRows[$key][(string) $parentSourceId] ?? null;
            if (!$parent) {
                throw new RuntimeException('Missing imported category parent.');
            }
            $result = $this->category($parent, $context, [...$seen, (string) $row['id']]);
            $parentId = $context->remember('wiki_categories', $parent, $result)['target_id'];
        }
        $roles = null;
        $review = false;
        if (array_key_exists('roles', $row) && $row['roles'] !== null) {
            $roles = is_array($row['roles']) ? $row['roles'] : json_decode((string) $row['roles'], true);
            $review = !is_array($roles) || !array_is_list($roles);
            $roles = $review ? [] : $roles;
        }
        $mappedRoles = [];
        foreach ($roles ?? [] as $role) {
            $mapped = is_int($role) || is_string($role) ? $context->mapped('roles', $role) : null;
            if ($mapped === null) {
                $context->report('review', [
                    'plugin' => 'wiki',
                    'table' => 'wiki_categories',
                    'id' => $row['id'],
                    'reason' => 'unmapped_role',
                ]);

                continue;
            }
            $mappedRoles[] = $mapped;
        }
        $id = $this->writer->category(
            $context,
            $row['id'],
            [
                'name' => (string) $row['name'],
                'parent_id' => $parentId,
                'description' => $row['description'] ?? '',
                'position' => $row['position'] ?? ($row['order'] ?? 0),
                'enabled' => (bool) ($row['is_enabled'] ?? true),
                'read_access' => $roles === null ? 'public' : 'roles',
                'permission' => $review ? 'imports.review' : null,
                'icon' => preg_replace('/^(?:bi|fas?|far)\\s+(?:bi-|fa-)/', '', (string) ($row['icon'] ?? 'book')),
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ],
            $mappedRoles,
        );
        if ($review) {
            $context->report('review', [
                'plugin' => 'wiki',
                'table' => 'wiki_categories',
                'id' => $row['id'],
                'reason' => 'invalid_access_rules',
            ]);
        }
        if (!empty($row['miniature'])) {
            $context->report('media', [
                'plugin' => 'wiki',
                'table' => 'wiki_categories',
                'id' => $row['id'],
                'field' => 'miniature',
                'path' => $row['miniature'],
            ]);
        }

        return [
            'target_table' => 'wiki__categories',
            'target_id' => $id,
            'metadata' => ['name' => $row['name'], 'slug' => $row['slug'] ?? null],
        ];
    }
}
