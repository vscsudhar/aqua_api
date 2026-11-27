<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $staffA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Aqua Co A',
            'company_code' => 'EXP-A',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Aqua Co B',
            'company_code' => 'EXP-B',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->staffA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_default_categories_seeded_and_custom_category_rules(): void
    {
        Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/expense-categories');
        $res->assertStatus(200);
        $this->assertGreaterThanOrEqual(12, count($res->json('data')));
        $this->assertDatabaseHas('expense_categories', ['name' => 'Fuel', 'is_default' => true, 'company_id' => null]);

        // Create custom category
        $createRes = $this->postJson('/api/v1/expense-categories', [
            'name' => 'Generator Diesel',
            'description' => 'Backup generator fuel',
        ]);
        $createRes->assertStatus(201)->assertJsonPath('data.name', 'Generator Diesel');
        $catId = $createRes->json('data.id');

        // Duplicate name prevented within same company
        $dupRes = $this->postJson('/api/v1/expense-categories', [
            'name' => 'generator diesel',
        ]);
        $dupRes->assertStatus(422);

        // Same custom name allowed in Company B
        Sanctum::actingAs($this->userB);
        $this->postJson('/api/v1/expense-categories', [
            'name' => 'Generator Diesel',
        ])->assertStatus(201);

        // Company B cannot edit or delete Company A's category
        $this->putJson("/api/v1/expense-categories/{$catId}", ['name' => 'Hacked'])->assertStatus(404);
        $this->deleteJson("/api/v1/expense-categories/{$catId}")->assertStatus(404);

        // Company A can edit & disable custom category
        Sanctum::actingAs($this->userA);
        $this->putJson("/api/v1/expense-categories/{$catId}", [
            'name' => 'DG Diesel',
            'status' => 'inactive',
        ])->assertStatus(200)->assertJsonPath('data.status', 'inactive');
    }

    public function test_create_expense_with_and_without_attachments_and_validation(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userA);

        $catRes = $this->getJson('/api/v1/expense-categories');
        $fuelId = collect($catRes->json('data'))->firstWhere('name', 'Fuel')['id'];
        $today = Carbon::today('Asia/Kolkata')->toDateString();

        // Invalid input (amount <= 0, invalid payment mode)
        $this->postJson('/api/v1/expenses', [
            'category_id' => $fuelId,
            'amount' => 0,
            'expense_date' => $today,
            'payment_mode' => 'Crypto',
        ])->assertStatus(422);

        // 1. Without attachment
        $noAttach = $this->postJson('/api/v1/expenses', [
            'category_id' => $fuelId,
            'amount' => 450.50,
            'expense_date' => $today,
            'payment_mode' => 'Cash',
            'paid_to' => 'IndianOil Pump',
            'description' => 'Delivery van diesel',
        ]);
        $noAttach->assertStatus(201)
            ->assertJsonPath('data.has_attachment', false)
            ->assertJsonMissing(['attachment_path']);

        // 2. With Image attachment
        $img = UploadedFile::fake()->image('bill.jpg', 300, 300);
        $withImg = $this->post('/api/v1/expenses', [
            'category_id' => $fuelId,
            'amount' => 1200.00,
            'expense_date' => $today,
            'payment_mode' => 'UPI',
            'attachment' => $img,
        ]);
        $withImg->assertStatus(201)->assertJsonPath('data.has_attachment', true);
        $imgExpenseId = $withImg->json('data.id');

        // Authenticated attachment download works for Company A
        $this->get("/api/v1/expenses/{$imgExpenseId}/attachment")->assertStatus(200);

        // 3. With PDF attachment
        $pdf = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');
        $withPdf = $this->post('/api/v1/expenses', [
            'category_id' => $fuelId,
            'amount' => 2500.00,
            'expense_date' => $today,
            'payment_mode' => 'Bank',
            'attachment' => $pdf,
        ]);
        $withPdf->assertStatus(201)->assertJsonPath('data.has_attachment', true);
        $pdfExpenseId = $withPdf->json('data.id');
        $this->get("/api/v1/expenses/{$pdfExpenseId}/attachment")->assertStatus(200);

        // Staff user in Company A can view all Company A expenses
        Sanctum::actingAs($this->staffA);
        $this->getJson('/api/v1/expenses')->assertStatus(200)->assertJsonPath('meta.total', 3);

        // Company B cannot view or download Company A's expense or attachment
        Sanctum::actingAs($this->userB);
        $this->getJson("/api/v1/expenses/{$imgExpenseId}")->assertStatus(404);
        $this->get("/api/v1/expenses/{$imgExpenseId}/attachment")->assertStatus(404);

        // Manually sending X-Company-Id header cannot bypass company isolation
        $this->withHeaders(['X-Company-Id' => (string) $this->companyA->id])
            ->getJson("/api/v1/expenses/{$imgExpenseId}")
            ->assertStatus(404);
        $this->withHeaders(['X-Company-Id' => (string) $this->companyA->id])
            ->getJson('/api/v1/expenses')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_edit_replace_remove_attachment_and_soft_delete(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userA);

        $catRes = $this->getJson('/api/v1/expense-categories');
        $rentId = collect($catRes->json('data'))->firstWhere('name', 'Rent')['id'];
        $today = Carbon::today('Asia/Kolkata')->toDateString();

        $file1 = UploadedFile::fake()->image('receipt1.png', 100, 100);
        $created = $this->post('/api/v1/expenses', [
            'category_id' => $rentId,
            'amount' => 5000,
            'expense_date' => $today,
            'payment_mode' => 'Bank',
            'attachment' => $file1,
        ])->assertStatus(201);

        $expenseId = $created->json('data.id');
        $oldPath = Expense::find($expenseId)->attachment_path;
        Storage::disk('local')->assertExists($oldPath);

        // Replace attachment via POST (Flutter Web multipart pattern)
        $file2 = UploadedFile::fake()->create('receipt2.pdf', 50, 'application/pdf');
        $this->post("/api/v1/expenses/{$expenseId}", [
            '_method' => 'PUT',
            'amount' => 5500,
            'attachment' => $file2,
        ])->assertStatus(200)->assertJsonPath('data.amount', '5500.00');

        $newPath = Expense::find($expenseId)->attachment_path;
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // Remove attachment
        $this->putJson("/api/v1/expenses/{$expenseId}", [
            'remove_attachment' => true,
        ])->assertStatus(200)->assertJsonPath('data.has_attachment', false);
        Storage::disk('local')->assertMissing($newPath);

        // Cannot delete category that is used by an expense
        $custom = $this->postJson('/api/v1/expense-categories', ['name' => 'Custom Used'])->assertStatus(201);
        $customId = $custom->json('data.id');
        $this->postJson('/api/v1/expenses', [
            'category_id' => $customId,
            'amount' => 100,
            'expense_date' => $today,
            'payment_mode' => 'Cash',
        ])->assertStatus(201);
        $this->deleteJson("/api/v1/expense-categories/{$customId}")->assertStatus(422);

        // Soft delete expense and verify excluded from summary & report
        $this->deleteJson("/api/v1/expenses/{$expenseId}")->assertStatus(200);
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);

        $summary = $this->getJson('/api/v1/expenses/summary')->assertStatus(200);
        $this->assertEquals(100.0, (float) $summary->json('data.total_expenses'));
        $this->assertEquals(1, (int) $summary->json('data.total_expense_count'));
    }

    public function test_summary_report_and_pagination(): void
    {
        Sanctum::actingAs($this->userA);

        $catRes = $this->getJson('/api/v1/expense-categories');
        $cats = collect($catRes->json('data'));
        $fuelId = $cats->firstWhere('name', 'Fuel')['id'];
        $elecId = $cats->firstWhere('name', 'Electricity')['id'];

        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $yesterday = Carbon::yesterday('Asia/Kolkata')->toDateString();

        $this->postJson('/api/v1/expenses', [
            'category_id' => $fuelId,
            'amount' => 300,
            'expense_date' => $today,
            'payment_mode' => 'Cash',
        ])->assertStatus(201);

        $this->postJson('/api/v1/expenses', [
            'category_id' => $elecId,
            'amount' => 700,
            'expense_date' => $yesterday,
            'payment_mode' => 'UPI',
        ])->assertStatus(201);

        $report = $this->getJson("/api/v1/expenses/report?from_date={$yesterday}&to_date={$today}")
            ->assertStatus(200);

        $this->assertEquals(1000.0, (float) $report->json('data.summary.total_expenses'));
        $this->assertEquals(300.0, (float) $report->json('data.summary.cash_expenses'));
        $this->assertEquals(700.0, (float) $report->json('data.summary.upi_expenses'));
        $this->assertEquals(0.0, (float) $report->json('data.summary.bank_expenses'));
        $this->assertCount(2, $report->json('data.category_summary'));
        $this->assertEquals(70.0, (float) $report->json('data.category_summary.0.percentage'));

        // Pagination check
        for ($i = 0; $i < 22; $i++) {
            $this->postJson('/api/v1/expenses', [
                'category_id' => $fuelId,
                'amount' => 10,
                'expense_date' => $today,
                'payment_mode' => 'Cash',
            ])->assertStatus(201);
        }

        $page1 = $this->getJson('/api/v1/expenses?per_page=10&page=1')->assertStatus(200);
        $this->assertCount(10, $page1->json('data'));
        $this->assertEquals(24, $page1->json('meta.total'));
        $this->assertEquals(3, $page1->json('meta.last_page'));

        // Bulk delete check (only Company A expenses deleted, soft-deleted)
        $idsToDelete = collect($page1->json('data'))->take(3)->pluck('id')->all();
        $bulkRes = $this->postJson('/api/v1/expenses/bulk-delete', ['ids' => $idsToDelete])
            ->assertStatus(200);
        $this->assertEquals(3, (int) $bulkRes->json('data.deleted_count'));
        foreach ($idsToDelete as $delId) {
            $this->assertSoftDeleted('expenses', ['id' => $delId]);
        }
    }
}
