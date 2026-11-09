<?php

namespace KraftInk\Plugins\Wiki\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin Builder<static>
 *
 * @property int $resource_id
 * @property string $image_id
 * @property bool $active
 */
class DocumentImage extends Pivot
{
    protected $table = 'wiki__document_images';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'resource_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }
}
