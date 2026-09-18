<?php

namespace Tests\Feature;

use App\Jobs\DispatchSmsJob;
use App\Models\ApiKey;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\SmsPricingSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NetworkSmsSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_network_sms_can_be_queued_with_sender_address_without_service_code(): void
    {
        Bus::fake();
        Config::set('services.mtn.enabled', true);
        Config::set('services.mtn.service_code', null);

        SmsPricingSetting::current()->update([
            'price_per_sms' => 2,
            'currency' => 'XOF',
            'network_enabled' => true,
        ]);

        $user = User::create([
            'name' => 'Client MTN',
            'email' => 'client-mtn@example.test',
            'password' => Hash::make('password'),
            'role' => 'Client',
            'status' => 'actif',
            'email_verified_at' => now(),
        ]);

        Organisation::create([
            'user_id' => $user->id,
            'name' => 'Client MTN',
            'mtn_sender_address' => 'SMSGW',
            'mtn_country_code' => '229',
            'preferred_sms_channel' => 'network',
        ]);

        $plan = Plan::create([
            'name' => 'Business',
            'price' => 1000,
            'currency' => 'XOF',
            'sms_quota_monthly' => 100,
            'max_devices' => 0,
            'active' => true,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'channel' => 'network',
            'duration_months' => 1,
            'sms_used' => 0,
            'extra_sms_credit' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => 'Live',
            'environment' => 'live',
            'key' => 'sk_live_network_test',
            'secret' => 'ss_live_network_test',
            'status' => 'active',
        ]);

        $response = $this
            ->withToken($apiKey->key)
            ->postJson('/api/v1/sms/send', [
                'to' => '0197000000',
                'message' => 'Bonjour',
            ]);

        $response->assertCreated();
        Bus::assertDispatched(DispatchSmsJob::class);

        $this->assertDatabaseHas('sms_messages', [
            'user_id' => $user->id,
            'recipient' => '0197000000',
            'status' => 'pending',
        ]);
    }
}
