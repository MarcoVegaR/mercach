<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Spatie\Permission\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = ['Sales', 'Marketing', 'Engineering', 'HR', 'Finance', 'Operations', 'Support', 'Product', 'Legal', 'QA'];
        $levels = ['Junior', 'Senior', 'Lead', 'Manager', 'Director', 'VP', 'Executive'];
        $specializations = ['Specialist', 'Analyst', 'Coordinator', 'Administrator', 'Consultant', 'Architect', 'Developer', 'Designer'];

        $department = $this->faker->randomElement($departments);
        $level = $this->faker->randomElement($levels);
        $specialization = $this->faker->randomElement($specializations);

        // Generate more realistic role names
        $rolePatterns = [
            "{$department} {$level}",
            "{$level} {$specialization}",
            "{$department} {$specialization}",
            "{$level} {$department} {$specialization}",
        ];

        $roleName = $this->faker->randomElement($rolePatterns);

        return [
            'name' => $roleName.' '.$this->faker->unique()->numberBetween(1, 9999),
            // Use 'web' guard to match configured guards and avoid relation issues
            'guard_name' => 'web',
            'is_active' => $this->faker->boolean(80), // 80% probability of being active
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Role $role) {
            // Clear permission cache to ensure we get fresh data
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            // Assign random permissions to each role
            $allPermissions = \Spatie\Permission\Models\Permission::where('guard_name', $role->guard_name)
                ->pluck('name')
                ->toArray();

            if (! empty($allPermissions)) {
                // Give each role between 2-8 random permissions (reduced to avoid conflicts)
                $permissionCount = $this->faker->numberBetween(2, min(8, count($allPermissions)));
                $randomPermissions = $this->faker->randomElements($allPermissions, $permissionCount);

                $role->givePermissionTo($randomPermissions);
            }
        });
    }
}
