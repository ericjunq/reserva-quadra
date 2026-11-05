<?php

namespace App\Exceptions;

use Exception;

class RegraNegocioException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $dados = [],
    ) {
        parent::__construct($message);
    }
}
