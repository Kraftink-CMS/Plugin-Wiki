<?php

namespace KraftInk\Plugins\Wiki\Services\Imports\MineWeb;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\MappingRegistry;
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
        $registry->register('mineweb', 'wiki__wcategories', $this->category(...), 45, 'wiki');
        $registry->register(
            'mineweb',
            'wiki__registre',
            $this->document(...),
            50,
            'wiki',
            $this->writer->replacements(...),
            dependencies: ['wiki__wcategories'],
        );
    }

    private function document(array $row, ImportContext $context): array
    {
        $sourceCategoryId = $row['cate_id'] ?? null;
        $categoryId = $sourceCategoryId ? $context->required('wiki__wcategories', $sourceCategoryId) : null;
        $result = $this->writer->document($context, $row['id'], [
            'slug' => $row['slug'] ?? $row['name'],
            'title' => $row['name'],
            'body' => $row['desc'],
            'category_id' => $categoryId,
            'position' => $row['position'] ?? ($row['order'] ?? 0),
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ]);

        if (!empty($row['miniature'])) {
            $context->report('media', [
                'plugin' => 'wiki',
                'table' => 'wiki__registre',
                'id' => $row['id'],
                'field' => 'miniature',
                'path' => $row['miniature'],
            ]);
        }
        $context->report('review', [
            'plugin' => 'wiki',
            'table' => 'wiki__registre',
            'id' => $row['id'],
            'reason' => 'ajax_document_navigation',
        ]);

        return $result + ['metadata' => []];
    }

    private function category(array $row, ImportContext $context, array $seen = []): array
    {
        $key = $context->runId;
        if ($mapped = $context->mapped('wiki__wcategories', $row['id'])) {
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
            foreach ($context->sourceRows('wiki__wcategories') ?? [] as $item) {
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
            $parentId = $context->remember('wiki__wcategories', $parent, $result)['target_id'];
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
            $mapped = is_int($role) || is_string($role) ? $context->mapped('ranks', $role) : null;
            if ($mapped === null) {
                $context->report('review', [
                    'plugin' => 'wiki',
                    'table' => 'wiki__wcategories',
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
                'table' => 'wiki__wcategories',
                'id' => $row['id'],
                'reason' => 'invalid_access_rules',
            ]);
        }
        if (!empty($row['miniature'])) {
            $context->report('media', [
                'plugin' => 'wiki',
                'table' => 'wiki__wcategories',
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
