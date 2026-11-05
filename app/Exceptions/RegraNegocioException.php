<?php

namespace App\Exceptions;

use Exception;

class RegraNegocioException extends Exception
{
    /**
     * @param int $status 409 para conflitos (horário, estoque); 422 para as demais regras.
     * @param array<string, mixed> $dados Informações extras devolvidas no JSON.
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $dados = [],
    ) {
        parent::__construct($message);
    }
}
