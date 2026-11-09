<?php

namespace Database\Seeders;

use App\Constants\TipoEspacoConstant;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Equipamento;
use App\Models\Espaco;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $quadra = Espaco::firstOrCreate(
            ['nome' => 'Quadra Poliesportiva'],
            ['tipo' => TipoEspacoConstant::QUADRA, 'limite_semanal_por_curso' => 3],
        );
        $labQuimica = Espaco::firstOrCreate(['nome' => 'Laboratório de Química'], ['tipo' => TipoEspacoConstant::LABORATORIO]);
        $labBiologia = Espaco::firstOrCreate(['nome' => 'Laboratório de Biologia'], ['tipo' => TipoEspacoConstant::LABORATORIO]);
        Espaco::firstOrCreate(['nome' => 'Laboratório de Informática'], [
            'tipo'                   => TipoEspacoConstant::LABORATORIO,
            'modo_horario'           => 'LIVRE',
            'abre_as'                => '07:00',
            'fecha_as'               => '22:00',
            'duracao_maxima_minutos' => 120,
        ]);
        Espaco::firstOrCreate(['nome' => 'Sala 101'], ['tipo' => TipoEspacoConstant::SALA]);
        Espaco::firstOrCreate(['nome' => 'Sala 102'], ['tipo' => TipoEspacoConstant::SALA]);

        $equipamentos = [
            [$quadra, 'Bola de futsal', 4, false],
            [$quadra, 'Bola de vôlei', 3, false],
            [$quadra, 'Bola de basquete', 3, false],
            [$quadra, 'Coletes (jogo)', 2, false],
            [$labBiologia, 'Microscópio', 2, true],
            [$labQuimica, 'Kit de vidrarias', 5, false],
            [$labQuimica, 'Projetor portátil', 1, true],
        ];

        foreach ($equipamentos as [$espaco, $nome, $quantidade, $compartilhavel]) {
            Equipamento::firstOrCreate(
                ['espaco_id' => $espaco->id, 'nome' => $nome],
                ['quantidade_total' => $quantidade, 'compartilhavel' => $compartilhavel],
            );
        }

        $alunos = [
            ['Ana Souza', '2024000001', 'TI'],
            ['Bruno Lima', '2024000002', 'TI'],
            ['Carla Mendes', '2024000003', 'Educação Física'],
            ['Diego Rocha', '2024000004', 'Medicina'],
            ['Eduarda Alves', '2024000005', 'Direito'],
        ];

        foreach ($alunos as [$nome, $ra, $curso]) {
            Aluno::firstOrCreate(
                ['registro_academico' => $ra],
                [
                    'nome_completo' => $nome,
                    'email'         => strtolower(strtok($nome, ' ')).".{$ra}@exemplo.com",
                    'curso_id'      => Curso::firstOrCreate(['nome' => $curso])->id,
                ],
            );
        }
    }
}
