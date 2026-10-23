<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    protected $fillable = ["nome_completo", "registro_academico", "email", "curso"];
}
