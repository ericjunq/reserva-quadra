<?php

namespace App\Models;

use App\Constants\AcaoAuditoriaConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['acao', 'antes', 'depois', 'user_id'];

    protected function casts(): array
    {
        return [
            'acao'   => AcaoAuditoriaConstant::class,
            'antes'  => 'array',
            'depois' => 'array',
        ];
    }

    public function auditavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
