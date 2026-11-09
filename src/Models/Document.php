<?php

namespace KraftInk\Plugins\Wiki\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use App\Kraft\Auth\PermissionAccess;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KraftInk\Plugins\Wiki\Services\CategoryAccess;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int|null $category_id
 * @property string|null $translation_group
 * @property string $locale
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $noindex
 * @property int $position
 * @property string|null $permission
 * @property bool $published
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read Collection<int, FileAttachment> $files
 * @property-read Collection<int, Image> $images
 */
class Document extends Model
{
    protected $table = 'wiki__documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'slug_category' => 'integer',
            'category_id' => 'integer',
            'noindex' => 'boolean',
            'position' => 'integer',
            'published' => 'boolean',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id');
    }

    public function imageUsages(): HasMany
    {
        return $this->hasMany(DocumentImage::class, 'resource_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileAttachment::class, 'document_id');
    }

    public function scopeReadableBy(Builder $query, ?User $user): Builder
    {
        $categories = app(CategoryAccess::class)->readableIds($user);
        $query
            ->whereNull('deleted_at')
            ->where('published', true)
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereIn('category_id', $categories));
        $access = app(PermissionAccess::class);

        return $access->filterQuery($query, 'permission', $access->permissions($user), 'wiki.view');
    }

    public function scopeIndexable(Builder $query): Builder
    {
        return $query->readableBy(null)->where('noindex', false);
    }

    public function scopeVisibleTo(Builder $query, ?User $user, ?string $locale = null): Builder
    {
        return $query->readableBy($user)->where('locale', $locale ?? app()->getLocale());
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'wiki__document_images', 'resource_id', 'image_id')
            ->using(DocumentImage::class)
            ->withPivot('active');
    }

    public function recordRevision(int $actor): DocumentRevision
    {
        return $this->revisions()->create([
            'user_id' => $actor,
            'snapshot' => $this->attributesToArray(),
        ]);
    }
}
