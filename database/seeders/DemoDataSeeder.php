<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\DeviceSim;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\SmsMessage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ---------- Compte Admin ----------
        $admin = User::updateOrCreate(
            ['email' => 'admin@smsgateway.test'],
            [
                'name' => 'Admin SMS Gateway',
                'password' => Hash::make('password'),
                'role' => 'Admin',
                'status' => 'actif',
                'email_verified_at' => now(),
            ]
        );

        // ---------- Compte Client de démo ----------
        $client = User::updateOrCreate(
            ['email' => 'client@smsgateway.test'],
            [
                'name' => 'Client Démo',
                'password' => Hash::make('password'),
                'role' => 'Client',
                'status' => 'actif',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✔ Utilisateurs : admin@smsgateway.test / client@smsgateway.test (mdp: password)');

        // ---------- Organisation ----------
        Organisation::updateOrCreate(
            ['user_id' => $client->id],
            [
                'name' => 'Boutique Démo SARL',
                'signature' => '- Boutique Démo',
                'website' => 'https://boutique-demo.test',
                'phone' => '+229 90 00 00 00',
                'address' => 'Cotonou, Bénin',
            ]
        );

        // ---------- Abonnement ----------
        $plan = Plan::where('name', 'Business')->first();

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $client->id, 'status' => 'active'],
            [
                'plan_id' => $plan?->id,
                'sms_used' => 12,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ]
        );

        // ---------- Clé API ----------
        $apiKey = ApiKey::updateOrCreate(
            ['user_id' => $client->id, 'name' => 'Clé de test'],
            [
                'key' => 'sk_test_' . Str::random(32),
                'secret' => Str::random(48),
                'status' => 'active',
            ]
        );

        $this->command->info("✔ Clé API de test : {$apiKey->key}");

        // ---------- Device + SIMs (multi-SIM) ----------
        $device = Device::updateOrCreate(
            ['user_id' => $client->id, 'name' => 'Téléphone Bureau'],
            [
                'device_token' => 'dev_test_' . Str::random(40),
                'android_device_id' => 'android-' . Str::random(12),
                'status' => 'online',
                'fcm_token' => 'fcm_fake_token_' . Str::random(20),
                'battery_level' => 87,
                'last_seen_at' => now(),
            ]
        );

        $this->command->info("✔ Device token de test : {$device->device_token}");

        $sim1 = DeviceSim::updateOrCreate(
            ['device_id' => $device->id, 'slot_index' => 0],
            [
                'phone_number' => '+22990111111',
                'operator' => 'MTN',
                'is_active' => true,
                'daily_quota' => 200,
                'sent_today' => 5,
                'signal_strength' => 4,
            ]
        );

        DeviceSim::updateOrCreate(
            ['device_id' => $device->id, 'slot_index' => 1],
            [
                'phone_number' => '+22990222222',
                'operator' => 'Moov',
                'is_active' => true,
                'daily_quota' => 200,
                'sent_today' => 0,
                'signal_strength' => 3,
            ]
        );

        // ---------- Webhook ----------
        Webhook::updateOrCreate(
            ['user_id' => $client->id, 'event' => 'sms.delivered'],
            [
                'url' => 'https://webhook.site/test-endpoint',
                'secret' => Str::random(40),
                'active' => true,
            ]
        );

        // ---------- SMS de démonstration (différents statuts) ----------
        $samples = [
            ['recipient' => '+22997000001', 'status' => 'delivered'],
            ['recipient' => '+22997000002', 'status' => 'sent'],
            ['recipient' => '+22997000003', 'status' => 'failed'],
            ['recipient' => '+22997000004', 'status' => 'queued'],
            ['recipient' => '+22997000005', 'status' => 'pending'],
        ];

        foreach ($samples as $sample) {
            $sms = SmsMessage::create([
                'user_id' => $client->id,
                'api_key_id' => $apiKey->id,
                'device_sim_id' => in_array($sample['status'], ['sent', 'delivered', 'queued']) ? $sim1->id : null,
                'recipient' => $sample['recipient'],
                'content' => 'Ceci est un message de test - Boutique Démo',
                'status' => $sample['status'],
                'sent_at' => in_array($sample['status'], ['sent', 'delivered']) ? now()->subMinutes(10) : null,
                'delivered_at' => $sample['status'] === 'delivered' ? now()->subMinutes(8) : null,
                'error_message' => $sample['status'] === 'failed' ? 'Numéro invalide' : null,
            ]);

            $sms->statusLogs()->create(['status' => $sample['status']]);
        }

        $this->command->info('✔ 5 SMS de démonstration créés (tous statuts représentés)');
        $this->command->info('✔ Seeding terminé.');
    }
}
