<?php

namespace App\Models\Concerns;

use App\Constants\AcaoAuditoriaConstant;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait Auditavel
{
    private const CAMPOS_IGNORADOS = ['created_at', 'updated_at', 'atualizado_por'];

    public static function bootAuditavel(): void
    {
        static::creating(function (Model $model) {
            $model->criado_por ??= Auth::id();
            $model->atualizado_por = $model->criado_por;
        });

        static::updating(function (Model $model) {
            $model->atualizado_por = Auth::id();
        });

        static::created(fn (Model $model) => $model->registrarAuditoria(
            AcaoAuditoriaConstant::CRIACAO, null, $model->getAttributes(),
        ));

        static::updated(function (Model $model) {
            $depois = $model->getChanges();

            $model->registrarAuditoria(
                AcaoAuditoriaConstant::ATUALIZACAO,
                array_intersect_key($model->getRawOriginal(), $depois),
                $depois,
            );
        });

        static::deleted(fn (Model $model) => $model->registrarAuditoria(
            AcaoAuditoriaConstant::EXCLUSAO, $model->getRawOriginal(), null,
        ));
    }

    protected function camposSensiveisDaAuditoria(): array
    {
        return [];
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    public function auditorias(): MorphMany
    {
        return $this->morphMany(Auditoria::class, 'auditavel');
    }

    private function registrarAuditoria(AcaoAuditoriaConstant $acao, ?array $antes, ?array $depois): void
    {
        $antes = $this->normalizarParaAuditoria($antes);
        $depois = $this->normalizarParaAuditoria($depois);

        if ($acao === AcaoAuditoriaConstant::ATUALIZACAO && ! $depois) {
            return;
        }

        $this->auditorias()->create([
            'acao'    => $acao,
            'antes'   => $antes,
            'depois'  => $depois,
            'user_id' => Auth::id(),
        ]);
    }

    private function normalizarParaAuditoria(?array $valores): ?array
    {
        if ($valores === null) {
            return null;
        }

        $valores = array_diff_key($valores, array_flip(self::CAMPOS_IGNORADOS));

        foreach ($this->camposSensiveisDaAuditoria() as $campo) {
            if (array_key_exists($campo, $valores)) {
                $valores[$campo] = '[oculto]';
            }
        }

        foreach ($valores as $campo => $valor) {
            if ($valor !== null && $this->hasCast($campo, ['bool', 'boolean'])) {
                $valores[$campo] = (bool) $valor;
            } elseif ($valor !== null && $this->hasCast($campo, ['int', 'integer'])) {
                $valores[$campo] = (int) $valor;
            }
        }

        return $valores;
    }
}
