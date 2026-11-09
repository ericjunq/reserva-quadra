<?php

namespace App\Support;

class FrontEnd
{
    public static function url(string $caminho): string
    {
        return rtrim(config('app.frontend_url'), '/').'/'.ltrim($caminho, '/');
    }
}
