<?php

namespace App\Models;

use Database\Factories\SuppressionListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email', 'reason'])]
class SuppressionList extends Model
{
    /** @use HasFactory<SuppressionListFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'suppression_list';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
