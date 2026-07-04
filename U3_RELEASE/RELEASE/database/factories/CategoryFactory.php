<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name'        => ucwords($name),
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->optional()->sentence(),
            'parent_id'   => null,
            'created_by'  => User::factory(),
        ];
    }

    public function withParent(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
