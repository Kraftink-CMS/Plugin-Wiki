<?php

namespace KraftInk\Plugins\Wiki\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $user_id
 * @property array $snapshot
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class DocumentRevision extends Model
{
    protected $table = 'wiki__document_revisions';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'user_id' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'datetime',
            'id' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
