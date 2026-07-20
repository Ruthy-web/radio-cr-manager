<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hospital_id' => Hospital::factory(),
            'exam_template_id' => null,
            'patient_name' => fake()->name(),
            'patient_age' => (string) fake()->numberBetween(1, 90),
            'patient_sex' => fake()->randomElement(['M', 'F']),
            'file_number' => (string) fake()->numerify('DOS-#####'),
            'prescriber' => fake()->name(),
            'exam_date' => fake()->date(),
            'content' => [
                'heading' => 'Compte Rendu de Radiographie du Thorax',
                'identity' => ['side' => null],
                'indication' => null,
                'technique' => fake()->sentence(),
                'results' => [
                    ['text' => fake()->sentence(), 'abnormal' => false, 'heading' => false],
                ],
                'conclusion' => 'Examen normal.',
            ],
        ];
    }
}
