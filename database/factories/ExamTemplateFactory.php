<?php

namespace Database\Factories;

use App\Models\ExamTemplate;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamTemplate>
 */
class ExamTemplateFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Radiographie '.fake()->unique()->word();

        return [
            'hospital_id' => Hospital::factory(),
            'title' => $title,
            'heading' => 'Compte Rendu de '.$title,
            'modality' => 'radiographie',
            'requires_side' => false,
            'indication' => null,
            'technique' => fake()->sentence(),
            'results' => [
                ['text' => fake()->sentence(), 'abnormal' => false, 'heading' => false],
                ['text' => fake()->sentence(), 'abnormal' => false, 'heading' => false],
            ],
            'conclusion' => 'Examen normal.',
            'active' => true,
        ];
    }

    public function requiresSide(): static
    {
        return $this->state(fn (array $attributes) => ['requires_side' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
