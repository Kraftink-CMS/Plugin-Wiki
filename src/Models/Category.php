<?php

namespace KraftInk\Plugins\Wiki\Models;

use App\Models\Accounts\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $locale
 * @property string $name
 * @property string $icon
 * @property string|null $description
 * @property string $read_access
 * @property string|null $permission
 * @property bool $enabled
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $parent
 * @property-read Collection<int, CategoryRole> $categoryRoles
 * @property-read Collection<int, Role> $roles
 */
class Category extends Model
{
    protected $table = 'wiki__categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'enabled' => 'boolean',
            'position' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function categoryRoles(): HasMany
    {
        return $this->hasMany(CategoryRole::class, 'category_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    public static function descendantIds(int $id, ?SupportCollection $categories = null): array
    {
        $children = $categories?->groupBy('parent_id');
        $ids = [$id];
        $level = [$id];
        for ($depth = 0; $level; $depth++) {
            if ($children === null && ($depth >= 20 || count($ids) > 5000)) {
                throw ValidationException::withMessages(['parent_id' => __('wiki::messages.editor.invalid_parent')]);
            }
            $descendants = $children === null
                ? self::query()->whereIn('parent_id', $level)->pluck('id')
                : collect($level)->flatMap(fn (int $parent) => $children->get($parent, collect())->pluck('id'));
            $level = $descendants
                ->map(fn ($value) => (int) $value)
                ->diff($ids)
                ->values()
                ->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'wiki__category_roles', 'category_id', 'role_id')->using(
            CategoryRole::class,
        );
    }
}
