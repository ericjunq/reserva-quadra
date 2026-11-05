<?php

namespace Database\Seeders;

use App\Models\Curso;
use Illuminate\Database\Seeder;

class CursoSeeder extends Seeder
{
    public function run(): void
    {
        $cursos = [
            'Enfermagem',
            'Medicina',
            'Direito',
            'TI',
            'Educação Física',
            'Biomedicina',
            'Nutrição',
            'Engenharia',
            'Estética',
            'Fisioterapia',
            'Medicina Veterinária',
        ];

        foreach ($cursos as $nome) {
            Curso::firstOrCreate(['nome' => $nome]);
        }
    }
}
