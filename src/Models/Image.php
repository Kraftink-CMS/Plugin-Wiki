<?php

namespace KraftInk\Plugins\Wiki\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Models\Accounts\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder<static>
 *
 * @property string $id
 * @property int|null $uploader_id
 * @property string $path
 * @property string $hash
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Image extends Model
{
    use HasUuids;

    protected $table = 'wiki__images';

    protected $guarded = ['id'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploader_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function documentImages(): HasMany
    {
        return $this->hasMany(DocumentImage::class, 'image_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'wiki__document_images', 'image_id', 'resource_id')
            ->using(DocumentImage::class)
            ->withPivot('active');
    }
}
