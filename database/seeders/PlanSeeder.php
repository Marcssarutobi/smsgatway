<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'price' => 5000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 500,
                'max_devices' => 1,
                'active' => true,
            ],
            [
                'name' => 'Business',
                'price' => 20000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 3000,
                'max_devices' => 3,
                'active' => true,
            ],
            [
                'name' => 'Pro',
                'price' => 60000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 12000,
                'max_devices' => 10,
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan);
        }

        $this->command->info('✔ Plans créés : Starter, Business, Pro');
    }
}
