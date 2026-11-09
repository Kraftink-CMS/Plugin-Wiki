<?php

namespace KraftInk\Plugins\Wiki\Providers;

use App\Events\CollectingSeoPages;
use App\Events\CollectingSitemapEntries;
use App\Kraft\Features;
use App\Kraft\LocalePolicy;
use App\Kraft\Migration\MappingRegistry;
use App\Kraft\Widgets\WidgetRegistry;
use App\Models\Accounts\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use KraftInk\Plugins\Wiki\Listeners\DocumentRetention;
use KraftInk\Plugins\Wiki\Services\DocumentTrash;
use KraftInk\Plugins\Wiki\Services\Documents;
use KraftInk\Plugins\Wiki\Services\Imports\ImportMappings;
use App\Events\PruningData;
use App\Kraft\Trash\TrashRegistry;
use KraftInk\Plugins\Wiki\Models\Document;

class PluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PruningData::class, [DocumentRetention::class, 'prune']);
        $this->registerWidgets(app(WidgetRegistry::class));
        app(Features::class)->register('wiki', fn (?User $user) => app(Documents::class)->visible($user)->exists());
        Event::listen(CollectingSitemapEntries::class, function (CollectingSitemapEntries $event) {
            $event->source(function () use ($event) {
                $documents = app(Documents::class);
                $locales = app(LocalePolicy::class)->allowsChoice()
                    ? array_keys(config('kraft.locales'))
                    : [$event->locale];
                foreach (
                    Document::query()
                        ->indexable()
                        ->whereIn('locale', $locales)
                        ->select(['slug', 'locale', 'category_id', 'updated_at'])
                        ->orderBy('locale')
                        ->orderBy('id')
                        ->cursor() as $document
                ) {
                    yield [
                        'url' => $documents->url($document),
                        'modified' => $document->updated_at,
                    ];
                }
            });
        });
        app(ImportMappings::class)->register(app(MappingRegistry::class));
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'wiki');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'wiki');
        app(DocumentTrash::class)->register(app(TrashRegistry::class));
        Event::listen(CollectingSeoPages::class, function (CollectingSeoPages $event): void {
            $event->add(
                'plugins.wiki.public.documents.index',
                __('wiki::seo.pages.index'),
                [
                    'category' => __('wiki::seo.variables.category'),
                    'category_description' => __('wiki::seo.variables.category_description'),
                    'documents_count' => __('wiki::seo.variables.documents_count'),
                ],
                defaults: ['title' => '{category}', 'description' => '{description}'],
                excerptVariables: ['category_description'],
            );
            $event->add(
                'plugins.wiki.public.documents.show',
                __('wiki::seo.pages.document'),
                [
                    'document' => __('wiki::seo.variables.document'),
                    'document_description' => __('wiki::seo.variables.document_description'),
                    'category' => __('wiki::seo.variables.category'),
                ],
                defaults: ['title' => '{document}', 'description' => '{description}'],
                excerptVariables: ['document_description'],
            );
        });
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/admin.php');
    }

    private function registerWidgets(WidgetRegistry $widgets): void
    {
        $widgets->register(
            owner: 'wiki',
            id: 'recent-documents',
            label: 'wiki::widgets.recent_documents',
            render: fn (array $context, array $config) => view('wiki::widgets.recent-documents', [
                'documents' => app(Documents::class)
                    ->visible($context['user'] ?? null)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->limit($config['limit'])
                    ->get(['id', 'title', 'slug', 'locale', 'category_id']),
            ]),
            fields: ['limit' => ['type' => 'integer', 'label' => 'wiki::widgets.count', 'min' => 1, 'max' => 12]],
            permission: 'wiki.view',
            zones: ['home'],
            defaults: ['limit' => 5],
        );
    }
}
