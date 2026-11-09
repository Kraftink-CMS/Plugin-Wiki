<?php

namespace KraftInk\Plugins\Wiki\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 *
 * @property string $id
 * @property int $document_id
 * @property int|null $uploader_id
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property string $checksum
 * @property Carbon $created_at
 */
class FileAttachment extends Model
{
    use HasUuids;

    protected $table = 'wiki__files';

    protected $guarded = ['id'];

    public const null UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'document_id' => 'integer',
            'uploader_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
