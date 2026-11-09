<?php

namespace KraftInk\Plugins\Wiki\Services\Imports\KraftInk;

use App\Kraft\Migration\ImportContext;
use App\Kraft\Migration\ImportedHtml;
use App\Kraft\Migration\MappingRegistry;
use Illuminate\Support\Str;
use KraftInk\Plugins\Wiki\Services\Imports\Writer;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class Mappings
{
    private array $categories = [];

    public function register(MappingRegistry $registry): void
    {
        $registry->register('kraftink', 'wiki__categories', $this->category(...), 50, 'wiki');
        $registry->register(
            'kraftink',
            'wiki__documents',
            $this->document(...),
            55,
            'wiki',
            $this->replacements(...),
            dependencies: ['wiki__categories'],
        );
        $registry->register(
            'kraftink',
            'wiki__document_revisions',
            $this->revision(...),
            60,
            'wiki',
            dependencies: ['wiki__documents', 'users'],
        );
    }

    private function locale(array $row): string
    {
        return array_key_exists($row['locale'] ?? '', config('kraft.locales'))
            ? $row['locale']
            : config('imports.locale');
    }

    private function category(array $row, ImportContext $context, array $seen = []): array
    {
        if ($mapped = $context->mapped('wiki__categories', $row['id'])) {
            return ['target_table' => 'wiki__categories', 'target_id' => $mapped];
        }
        if (count($seen) >= 20 || in_array((string) $row['id'], $seen, true)) {
            throw new RuntimeException('Invalid imported category hierarchy.');
        }
        if (!isset($this->categories[$context->runId])) {
            $this->categories[$context->runId] = [];
            foreach ($context->sourceRows('wiki__categories') ?? [] as $item) {
                $this->categories[$context->runId][(string) $item['id']] = $item;
            }
        }
        $parent = null;
        if (!empty($row['parent_id'])) {
            $source = $this->categories[$context->runId][(string) $row['parent_id']] ?? null;
            if (!$source || $this->locale($source) !== $this->locale($row)) {
                throw new RuntimeException('Invalid imported category parent.');
            }
            $result = $this->category($source, $context, [...$seen, (string) $row['id']]);
            $parent = $context->remember('wiki__categories', $source, $result)['target_id'];
        }
        $id = $context->insert('wiki__categories', [
            'name' => strip_tags($row['name']),
            'locale' => $this->locale($row),
            'parent_id' => $parent,
            'description' => strip_tags($row['description'] ?? ''),
            'position' => max(0, (int) ($row['position'] ?? 0)),
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ]);

        return ['target_table' => 'wiki__categories', 'target_id' => $id];
    }

    private function data(array $row, ImportContext $context, bool $history = false): array
    {
        $group = $row['translation_group'] ?? null;
        $category = empty($row['category_id'])
            ? null
            : ($history
                ? $context->mapped('wiki__categories', $row['category_id'])
                : $context->required('wiki__categories', $row['category_id']));

        return [
            'locale' => $this->locale($row),
            'slug' => app(Writer::class)->importSlug(
                $row['slug'] ?? ($row['title'] ?? 'document'),
                $this->locale($row),
                $category,
                $context,
                $row['id'],
            ),
            'title' => strip_tags($row['title']),
            'body' => ImportedHtml::clean($row['body'] ?? ''),
            'category_id' => $category,
            'position' => max(0, (int) ($row['position'] ?? 0)),
            'permission' => 'imports.review',
            'published' => false,
            'translation_group' => is_string($group) && Str::isUuid($group)
                    ? Uuid::uuid5(Uuid::NAMESPACE_URL, 'wiki:'.$context->runId.':'.$group)->toString()
                    : null,
            'seo_title' => isset($row['seo_title']) ? strip_tags($row['seo_title']) : null,
            'seo_description' => isset($row['seo_description']) ? strip_tags($row['seo_description']) : null,
            'noindex' => (bool) ($row['noindex'] ?? false),
        ];
    }

    private function document(array $row, ImportContext $context): array
    {
        $data = $this->data($row, $context);
        $id = $context->insert(
            'wiki__documents',
            $data + ['created_at' => $row['created_at'] ?? now(), 'updated_at' => now()],
        );

        return ['target_table' => 'wiki__documents', 'target_id' => $id, 'metadata' => ['files' => 'archive_only']];
    }

    private function revision(array $row, ImportContext $context): array
    {
        $snapshot = json_decode($row['snapshot'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || !isset($snapshot['title'])) {
            throw new RuntimeException('Invalid imported document revision.');
        }
        $snapshot['id'] = $row['document_id'];
        $id = $context->insert('wiki__document_revisions', [
            'document_id' => $context->required('wiki__documents', $row['document_id']),
            'user_id' => empty($row['user_id']) ? null : $context->required('users', $row['user_id']),
            'snapshot' => json_encode($this->data($snapshot, $context, true), JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'] ?? now(),
        ]);

        return ['target_table' => 'wiki__document_revisions', 'target_id' => $id];
    }

    private function replacements(): array
    {
        return [
            'wiki__document_images' => [],
            'wiki__document_revisions' => [],
            'wiki__documents' => [],
            'wiki__categories' => [],
        ];
    }
}
