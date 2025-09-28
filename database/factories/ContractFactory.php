<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'number' => strtoupper($this->faker->bothify('C-###??')),
            'contract_type_id' => null,
            'contract_status_id' => null,
            'contract_modality_id' => null,
            'trade_category_id' => null,
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'billing_day' => $this->faker->numberBetween(1, 28),
            'monthly_price_eur' => $this->faker->randomFloat(2, 100, 10000),
            'pdf_path' => null,
            'is_active' => true,
        ];
    }
}
