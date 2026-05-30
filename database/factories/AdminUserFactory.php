<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AdminUserFactory extends Factory
{
    protected $model = \App\Models\AdminUser::class;
    public function definition(): array
    {
        return [
            'name'      => $this->faker->name(),
            'email'     => $this->faker->unique()->safeEmail(),
            'password'  => Hash::make('AdminForte123'),
            'role'      => 'suporte',
            'is_active' => true,
        ];
    }
    public function superAdmin(): static { return $this->state(['role' => 'super_admin']); }
}
