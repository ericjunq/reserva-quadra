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
        $horarioLivre = ['modo_horario' => 'LIVRE', 'abre_as' => '07:00', 'fecha_as' => '22:00', 'duracao_maxima_minutos' => 180];
        $labQuimica = Espaco::firstOrCreate(['nome' => 'Laboratório de Química'], ['tipo' => TipoEspacoConstant::LABORATORIO, ...$horarioLivre]);
        $labBiologia = Espaco::firstOrCreate(['nome' => 'Laboratório de Biologia'], ['tipo' => TipoEspacoConstant::LABORATORIO, ...$horarioLivre]);
        $labInformatica = Espaco::firstOrCreate(['nome' => 'Laboratório de Informática'], ['tipo' => TipoEspacoConstant::LABORATORIO, ...$horarioLivre]);
        $sala101 = Espaco::firstOrCreate(['nome' => 'Sala 101'], ['tipo' => TipoEspacoConstant::SALA, ...$horarioLivre]);
        $sala102 = Espaco::firstOrCreate(['nome' => 'Sala 102'], ['tipo' => TipoEspacoConstant::SALA, ...$horarioLivre]);

        $equipamentos = [
            [$quadra, 'Bola de futsal', 4, []],
            [$quadra, 'Bola de vôlei', 3, []],
            [$quadra, 'Bola de basquete', 3, []],
            [$quadra, 'Coletes (jogo)', 2, []],
            [$labBiologia, 'Microscópio', 2, [$labQuimica]],
            [$labQuimica, 'Kit de vidrarias', 5, []],
            [$labQuimica, 'Projetor portátil', 1, [$labBiologia, $labInformatica, $sala101, $sala102]],
        ];

        foreach ($equipamentos as [$espaco, $nome, $quantidade, $compartilhadoCom]) {
            Equipamento::firstOrCreate(
                ['espaco_id' => $espaco->id, 'nome' => $nome],
                ['quantidade_total' => $quantidade],
            )->espacosCompartilhados()->syncWithoutDetaching(collect($compartilhadoCom)->pluck('id'));
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
