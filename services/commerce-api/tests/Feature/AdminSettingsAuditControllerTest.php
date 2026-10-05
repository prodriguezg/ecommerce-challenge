<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\SettingType;
use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use LogicException;
use Tests\TestCase;

class AdminSettingsAuditControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_allowlisted_setting_reports_environment_and_database_values(): void
    {
        config(['api.checkout.reservation_timeout_seconds' => 120]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertExactJson([[
                'key' => 'reservation_timeout_seconds',
                'value' => 120,
                'source' => 'environment',
            ]]);

        $this->actingAs($admin)->putJson('/api/v1/admin/settings/reservation_timeout_seconds', ['value' => 300])
            ->assertOk()
            ->assertExactJson([
                'key' => 'reservation_timeout_seconds',
                'value' => 300,
                'source' => 'database',
            ]);

        $this->assertDatabaseHas('application_settings', [
            'key' => 'reservation_timeout_seconds',
            'value' => '300',
            'type' => SettingType::Integer->value,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action_code' => 'setting.updated',
        ]);
    }

    public function test_unknown_keys_invalid_values_and_unexpected_fields_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/settings/not_allowlisted', ['value' => 120])
            ->assertNotFound()
            ->assertJsonPath('code', 'setting_not_found');
        $this->actingAs($admin)->deleteJson('/api/v1/admin/settings/not_allowlisted')
            ->assertNotFound()
            ->assertJsonPath('code', 'setting_not_found');
        $this->actingAs($admin)->putJson('/api/v1/admin/settings/reservation_timeout_seconds', ['value' => 29])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');
        $this->actingAs($admin)->putJson('/api/v1/admin/settings/reservation_timeout_seconds', [
            'value' => 120,
            'unknown' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('unknown');

        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_removing_override_restores_fallback_without_changing_existing_expiration(): void
    {
        config(['api.checkout.reservation_timeout_seconds' => 120]);
        $admin = User::factory()->admin()->create();
        ApplicationSetting::factory()->create([
            'key' => 'reservation_timeout_seconds',
            'value' => '300',
            'type' => SettingType::Integer,
        ]);
        $reservation = Reservation::factory()->for(Order::factory())->create([
            'status' => ReservationStatus::Active,
            'expires_at' => '2026-10-05 12:05:00',
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/settings/reservation_timeout_seconds')
            ->assertOk()
            ->assertExactJson([
                'key' => 'reservation_timeout_seconds',
                'value' => 120,
                'source' => 'environment',
            ]);

        $this->assertDatabaseMissing('application_settings', ['key' => 'reservation_timeout_seconds']);
        $this->assertSame('2026-10-05 12:05:00', $reservation->fresh()->expires_at->format('Y-m-d H:i:s'));
        $audit = AuditLog::query()->where('action_code', 'setting.override_removed')->firstOrFail();
        $this->assertSame(300, $audit->before_data['value']);
        $this->assertSame('database', $audit->before_data['source']);
        $this->assertSame(120, $audit->after_data['value']);
        $this->assertSame('environment', $audit->after_data['source']);
    }

    public function test_audit_log_filters_by_actor_action_target_and_date(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'auditor@example.test']);
        $target = ApplicationSetting::factory()->create();
        $matching = AuditLog::factory()->for($admin, 'actor')->create([
            'action_code' => 'setting.updated',
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'before_data' => ['value' => 120],
            'after_data' => ['value' => 300],
            'created_at' => '2026-10-04 12:00:00',
        ]);
        AuditLog::factory()->for($admin, 'actor')->create([
            'action_code' => 'product.updated',
            'created_at' => '2026-10-03 12:00:00',
        ]);

        $targetType = rawurlencode('ApplicationSetting');
        $this->actingAs($admin)->getJson("/api/v1/admin/audit-logs?actor=auditor%40example.test&action=setting.updated&target={$targetType}&date_from=2026-10-04&date_to=2026-10-04")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $matching->id)
            ->assertJsonPath('items.0.metadata.before.value', 120)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_audit_rows_are_immutable(): void
    {
        $audit = AuditLog::factory()->create();

        $this->expectException(LogicException::class);
        AuditLog::unguarded(fn (): bool => $audit->update(['action_code' => 'changed']));
    }

    public function test_audit_data_recursively_redacts_credentials_tokens_and_card_input(): void
    {
        $admin = User::factory()->admin()->create();
        $target = ApplicationSetting::factory()->create();
        $request = Request::create('/api/v1/admin/settings/reservation_timeout_seconds', 'PUT');
        $request->setUserResolver(fn (): User => $admin);

        app(AdminAuditLogger::class)->record($request, 'setting.updated', $target, null, [
            'value' => 120,
            'nested' => [
                'password' => 'secret',
                'guest_token_hash' => 'token-hash',
                'payment_test_number' => '4000000000010001',
                'safe' => 'retained',
            ],
        ]);

        $after = AuditLog::query()->where('action_code', 'setting.updated')->sole()->after_data;
        $this->assertSame(['value' => 120, 'nested' => ['safe' => 'retained']], $after);
    }

    public function test_first_admin_creation_is_audited_without_credentials_or_personal_data(): void
    {
        $this->withHeader('Origin', (string) config('app.url'))->postJson('/api/v1/setup/admin', [
            'name' => 'First Administrator',
            'email' => 'admin@example.test',
            'password' => 'SecurePass1!',
        ])->assertCreated();

        $audit = AuditLog::query()->where('action_code', 'administrator.created')->firstOrFail();
        $encoded = json_encode($audit->after_data, JSON_THROW_ON_ERROR);

        $this->assertSame(['role' => 'admin'], $audit->after_data);
        $this->assertStringNotContainsString('SecurePass1!', $encoded);
        $this->assertStringNotContainsString('admin@example.test', $encoded);
        $this->assertNotNull($audit->actor_user_id);
    }
}
