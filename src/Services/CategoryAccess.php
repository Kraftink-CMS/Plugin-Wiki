<?php

namespace KraftInk\Plugins\Wiki\Services;

use App\Kraft\Auth\PermissionAccess;
use App\Models\Accounts\User;
use KraftInk\Plugins\Wiki\Models\Category;
use KraftInk\Plugins\Wiki\Models\CategoryRole;

class CategoryAccess
{
    public function readableIds(?User $user): array
    {
        if (!app(PermissionAccess::class)->allows($user, 'wiki.view')) {
            return [];
        }
        $categories = Category::query()->get()->keyBy('id');
        $identity = $user?->effectiveRoles() ?? collect();
        $roleIds = $identity->pluck('id')->all();
        $permissions = app(PermissionAccess::class)->permissions($user, $identity);
        $owner = in_array('*', $permissions, true);
        $roles = CategoryRole::query()->whereIn('role_id', $roleIds)->pluck('category_id')->flip();
        $allowed = [];
        foreach ($categories as $category) {
            /** @var Category|null $current */
            /** @var Category $category */
            $current = $category;
            $seen = [];
            while ($current) {
                if (
                    isset($seen[$current->id]) ||
                    count($seen) >= 20 ||
                    !$current->enabled ||
                    ($current->permission && !$owner && !in_array($current->permission, $permissions, true)) ||
                    !match ($current->read_access) {
                        'public' => true,
                        'members' => $user !== null,
                        'roles' => $user && ($owner || $roles->has($current->id)),
                        default => false,
                    }
                ) {
                    continue 2;
                }
                $seen[$current->id] = true;
                if ($current->parent_id && !isset($categories[$current->parent_id])) {
                    continue 2;
                }
                $current = $current->parent_id ? $categories[$current->parent_id] : null;
            }
            $allowed[] = (int) $category->id;
        }

        return $allowed;
    }
}
