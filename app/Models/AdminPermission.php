<?php

namespace App\Models;

use App\Enums\AdminSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property AdminSection $section
 * @property bool $can_view
 * @property bool $can_create
 * @property bool $can_update
 * @property bool $can_delete
 */
#[Fillable([
    'user_id',
    'section',
    'can_view',
    'can_create',
    'can_update',
    'can_delete',
])]
class AdminPermission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => AdminSection::class,
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
