<?php

namespace Database\Factories;

use App\Enums\TicketCategoria;
use App\Enums\TicketEstado;
use App\Enums\TicketOrigem;
use App\Enums\TicketPrioridade;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'categoria' => $this->faker->randomElement(TicketCategoria::cases())->value,
            'prioridade' => $this->faker->randomElement(TicketPrioridade::cases())->value,
            'estado' => TicketEstado::Aberto->value,
            'origem' => TicketOrigem::Admin->value,
            'titulo' => $this->faker->sentence(4),
            'descricao' => $this->faker->paragraph(),
        ];
    }
}
