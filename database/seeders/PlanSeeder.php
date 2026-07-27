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
                'name' => 'Trial',
                'price' => 0,
                'currency' => 'XOF',
                'sms_quota_monthly' => 50,   // quota limité pour l'essai
                'max_devices' => 1,
                'active' => true,
            ],
            [
                'name' => 'Starter',
                'price' => 5000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 500,
                'max_devices' => 1,
                'features' => [
                    'Support par email',
                    'Webhooks temps réel',
                    'Dashboard basique',
                ],
                'active' => true,
            ],
            [
                'name' => 'Business',
                'price' => 20000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 3000,
                'max_devices' => 3,
                'features' => [
                    'Support prioritaire 24/7',
                    'Webhooks temps réel',
                    'Statistiques avancées',
                ],
                'active' => true,
            ],
            [
                'name' => 'Pro',
                'price' => 60000,
                'currency' => 'XOF',
                'sms_quota_monthly' => 12000,
                'max_devices' => 10,
                'features' => [
                    'Support dédié + SLA garanti',
                    'Webhooks temps réel',
                    'Statistiques avancées',
                    'Multi-SIM automatique',
                    'Accès API prioritaire',
                ],
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan);
        }

        $this->command->info('✔ Plans créés : Starter, Business, Pro');
    }
}
