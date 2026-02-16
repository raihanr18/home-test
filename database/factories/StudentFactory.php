<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = ['Aiden', 'Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'Sophia', 'Jackson', 'Isabella', 'Lucas'];
        $lastNames = ['Adams', 'Baker', 'Brooks', 'Campbell', 'Davis', 'Evans', 'Foster', 'Hayes', 'Mitchell', 'Turner'];
        
        return [
            'nama' => fake()->randomElement($firstNames) . ' ' . fake()->randomElement($lastNames),
            'nim' => str_pad((string) fake()->unique()->numberBetween(1000000, 9999999999), 10, '0', STR_PAD_LEFT),
            'tanggal_lahir' => fake()->dateTimeBetween('2022-01-19', '2023-12-30')->format('Y-m-d'),
        ];
    }
}
