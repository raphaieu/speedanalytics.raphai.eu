<?php

namespace Tests\Feature\Demo;

use App\Models\DemoOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoManualApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_default_manual_account(): void
    {
        $response = $this->getJson('/api/demo/account');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'manual-default')
            ->assertJsonPath('data.current_balance', '100.00');
    }

    public function test_adjusts_bankroll_via_api(): void
    {
        $response = $this->postJson('/api/demo/account/adjust-bankroll', [
            'amount' => 10,
            'description' => 'Ajuste de teste',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.account.current_balance', '110.00')
            ->assertJsonPath('data.transaction.amount', '10.00');
    }

    public function test_creates_manual_operation_via_api(): void
    {
        $response = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_odd' => 2.75,
            'entry_position' => 2,
            'entry_color' => 'Vermelho',
            'risk_enforced' => false,
            'after_stop' => true,
            'tags' => ['entrada manual'],
            'entry_payload_json' => [
                'position' => 2,
                'color' => 'Vermelho',
                'odd' => 2.75,
            ],
            'note' => 'Entrada após stop.',
            'journal_tags' => ['FOMO'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.after_stop', true)
            ->assertJsonPath('data.journal.note', 'Entrada após stop.');

        $this->assertDatabaseHas('demo_operations', [
            'id' => $response->json('data.id'),
            'after_stop' => true,
        ]);
    }

    public function test_lists_open_and_settled_operations(): void
    {
        $open = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ])->json('data.id');

        $settled = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 1,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ])->json('data.id');

        $this->postJson("/api/demo/operations/{$settled}/settle", [
            'result' => 'loss',
            'profit_loss' => -1,
        ])->assertOk();

        $this->getJson('/api/demo/operations?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open);

        $this->getJson('/api/demo/operations?status=settled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $settled);
    }

    public function test_settles_operation_via_api_as_void(): void
    {
        $operationId = $this->postJson('/api/demo/operations', [
            'market_type' => 'winner',
            'bet_type' => 'single',
            'stake_amount' => 2,
            'entry_payload_json' => ['position' => 1],
            'risk_enforced' => false,
        ])->json('data.id');

        $response = $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'void',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'void')
            ->assertJsonPath('data.profit_loss', '0.00');

        $this->getJson('/api/demo/account')
            ->assertJsonPath('data.current_balance', '100.00');
    }

    public function test_rejects_settlement_without_amounts_for_win(): void
    {
        $operation = DemoOperation::query()->first()
            ?? $this->postJson('/api/demo/operations', [
                'market_type' => 'winner',
                'bet_type' => 'single',
                'stake_amount' => 1,
                'entry_payload_json' => ['position' => 1],
                'risk_enforced' => false,
            ])->json('data');

        $operationId = is_array($operation) ? $operation['id'] : $operation->id;

        $this->postJson("/api/demo/operations/{$operationId}/settle", [
            'result' => 'win',
        ])->assertStatus(422);
    }
}
