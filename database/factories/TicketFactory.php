<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['Received', 'Approved', 'Rejected', 'Completed']),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High']),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'user_id' => User::factory(),
        ];
    }
}
