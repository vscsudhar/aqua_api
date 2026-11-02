<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Dealer $dealer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Dashboard Tester',
            'email' => 'dash@marinaaqua.com',
            'username' => 'dashtest',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('TestDevice')->plainTextToken;

        $category = ProductCategory::create([
            'name' => 'Packaged Water',
            'code' => 'WATER',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => '20L Jar',
            'sku' => 'TEST-20L',
            'unit' => '20L Jar',
            'default_price' => 50.00,
            'cost_price' => 20.00,
            'tax_rate' => 18.00,
            'current_stock' => 5.00, // Low stock (min is 10)
            'min_stock_level' => 10.00,
            'is_active' => true,
        ]);

        $this->dealer = Dealer::create([
            'code' => 'DLR-T1',
            'name' => 'Test Dealer',
            'phone' => '+91 99999 11111',
            'credit_limit' => 20000.00,
            'opening_balance' => 1000.00,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard_summary(): void
    {
        $response = $this->getJson('/api/v1/dashboard/summary');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_dashboard_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'metrics' => [
                        'total_active_dealers',
                        'total_active_products',
                        'today_supply_quantity',
                        'today_supply_amount',
                        'today_collected_amount',
                        'total_outstanding_dealer_balance',
                        'low_stock_items_count',
                        'date',
                        'timezone',
                        'currency',
                    ],
                    'low_stock_items',
                    'recent_transactions',
                ],
            ]);
    }

    public function test_dashboard_aggregates_delivered_supplies_and_completed_payments_correctly(): void
    {
        $today = Carbon::today('Asia/Kolkata')->format('Y-m-d');

        // Delivered supply
        $supply = Supply::create([
            'supply_no' => 'SUP-TEST-1',
            'dealer_id' => $this->dealer->id,
            'supply_date' => $today,
            'subtotal' => 500.00,
            'tax_amount' => 90.00,
            'total_amount' => 590.00,
            'status' => 'delivered',
            'created_by' => $this->user->id,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 50.00,
            'tax_rate' => 18.00,
            'total_amount' => 590.00,
        ]);

        // Cancelled supply - must NOT be included in supply amount or quantity
        $cancelledSupply = Supply::create([
            'supply_no' => 'SUP-TEST-CAN',
            'dealer_id' => $this->dealer->id,
            'supply_date' => $today,
            'subtotal' => 200.00,
            'tax_amount' => 36.00,
            'total_amount' => 236.00,
            'status' => 'cancelled',
            'created_by' => $this->user->id,
        ]);

        SupplyItem::create([
            'supply_id' => $cancelledSupply->id,
            'product_id' => $this->product->id,
            'quantity' => 4.00,
            'unit_price' => 50.00,
            'tax_rate' => 18.00,
            'total_amount' => 236.00,
        ]);

        // Completed Payment
        Payment::create([
            'payment_no' => 'PAY-TEST-1',
            'dealer_id' => $this->dealer->id,
            'payment_date' => $today,
            'amount' => 300.00,
            'payment_mode' => 'cash',
            'status' => 'completed',
            'collected_by' => $this->user->id,
        ]);

        // Pending Payment - must NOT be included in collected amount
        Payment::create([
            'payment_no' => 'PAY-TEST-PENDING',
            'dealer_id' => $this->dealer->id,
            'payment_date' => $today,
            'amount' => 150.00,
            'payment_mode' => 'cheque',
            'status' => 'pending',
            'collected_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200);

        // Expected metrics:
        // Today supply amount: 590.00
        // Today supply quantity: 10.00
        // Today collected amount: 300.00
        // Total Outstanding: Opening (1000) + Delivered (590) - Completed Payments (300) = 1290.00
        $metrics = $response->json('data.metrics');
        $this->assertEquals(590.00, $metrics['today_supply_amount']);
        $this->assertEquals(10.00, $metrics['today_supply_quantity']);
        $this->assertEquals(300.00, $metrics['today_collected_amount']);
        $this->assertEquals(1290.00, $metrics['total_outstanding_dealer_balance']);
        $this->assertEquals(1, $metrics['low_stock_items_count']);
    }

    public function test_dashboard_charts_endpoint(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/dashboard/charts?days=7&months=3');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'daily_collections',
                    'monthly_sales',
                    'payment_modes',
                ],
            ]);

        $this->assertCount(7, $response->json('data.daily_collections'));
        $this->assertCount(3, $response->json('data.monthly_sales'));
    }
}
