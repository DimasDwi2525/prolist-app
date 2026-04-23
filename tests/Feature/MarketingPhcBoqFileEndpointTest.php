<?php

namespace Tests\Feature;

use App\Models\PHC;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingPhcBoqFileEndpointTest extends TestCase
{
    private array $createdTables = [];
    private array $createdUserIds = [];
    private array $createdRoleIds = [];
    private array $createdDepartmentIds = [];
    private array $createdProjectIds = [];
    private array $createdQuotationNumbers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->createMinimalSchema();
    }

    protected function tearDown(): void
    {
        DB::table('retentions')->whereIn('project_id', $this->createdProjectIds)->delete();
        DB::table('approvals')->whereIn('user_id', $this->createdUserIds)->delete();
        DB::table('notifications')->whereIn('notifiable_id', $this->createdUserIds)->delete();
        DB::table('phcs')->whereIn('project_id', $this->createdProjectIds)->delete();
        DB::table('projects')->whereIn('pn_number', $this->createdProjectIds)->delete();
        DB::table('quotations')->whereIn('quotation_number', $this->createdQuotationNumbers)->delete();
        DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        DB::table('roles')->whereIn('id', $this->createdRoleIds)->delete();
        DB::table('departments')->whereIn('id', $this->createdDepartmentIds)->delete();

        foreach (array_reverse($this->createdTables) as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_create_phc_stores_boq_file_path(): void
    {
        $this->withoutExceptionHandling();
        Storage::fake('public');
        Event::fake();

        $users = $this->seedUsers();
        $projectId = $this->seedProject();

        $response = $this->actingAs($users['creator'], 'api')->post('/api/phc', [
            'project_id' => $projectId,
            'pic_marketing_id' => $users['pic_marketing']->id,
            'ho_engineering_id' => $users['ho_engineering']->id,
            'client_pic_name' => 'PIC Client',
            'boq' => 'A',
            'boq_file_path' => UploadedFile::fake()->create('boq-create.pdf', 128, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $phc = PHC::findOrFail($response->json('data.phc.id'));

        $this->assertNotNull($phc->boq_file_path);
        $this->assertStringStartsWith('phc_boq_files/', $phc->boq_file_path);
        Storage::disk('public')->assertExists($phc->boq_file_path);
        $this->assertDatabaseHas('phcs', [
            'id' => $phc->id,
            'boq_file_path' => $phc->boq_file_path,
        ]);
    }

    public function test_update_phc_replaces_boq_file_path(): void
    {
        $this->withoutExceptionHandling();
        Storage::fake('public');
        Event::fake();

        $users = $this->seedUsers();
        $projectId = $this->seedProject();

        $createResponse = $this->actingAs($users['creator'], 'api')->post('/api/phc', [
            'project_id' => $projectId,
            'pic_marketing_id' => $users['pic_marketing']->id,
            'ho_engineering_id' => $users['ho_engineering']->id,
            'client_pic_name' => 'PIC Client',
            'boq' => 'A',
            'boq_file_path' => UploadedFile::fake()->create('boq-old.pdf', 128, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertOk();

        $phc = PHC::findOrFail($createResponse->json('data.phc.id'));
        $oldPath = $phc->boq_file_path;

        $updateResponse = $this->actingAs($users['creator'], 'api')->post("/api/phc/{$phc->id}", [
            'handover_date' => '2026-04-21',
            'start_date' => '2026-04-22',
            'target_finish_date' => '2026-05-22',
            'client_pic_name' => 'PIC Client Updated',
            'pic_marketing_id' => $users['pic_marketing']->id,
            'ho_engineering_id' => $users['ho_engineering']->id,
            'boq' => 'A',
            'costing_by_marketing' => 'A',
            'retention' => false,
            'warranty' => false,
            'boq_file_path' => UploadedFile::fake()->create('boq-new.pdf', 128, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $updateResponse->assertOk();

        $phc->refresh();

        $this->assertNotNull($phc->boq_file_path);
        $this->assertStringStartsWith('phc_boq_files/', $phc->boq_file_path);
        $this->assertNotSame($oldPath, $phc->boq_file_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($phc->boq_file_path);
        $this->assertDatabaseHas('phcs', [
            'id' => $phc->id,
            'boq_file_path' => $phc->boq_file_path,
        ]);
    }

    public function test_view_phc_boq_file_returns_file_response(): void
    {
        Storage::fake('public');
        Event::fake();

        $users = $this->seedUsers();
        $projectId = $this->seedProject();

        $createResponse = $this->actingAs($users['creator'], 'api')->post('/api/phc', [
            'project_id' => $projectId,
            'pic_marketing_id' => $users['pic_marketing']->id,
            'ho_engineering_id' => $users['ho_engineering']->id,
            'client_pic_name' => 'PIC Client',
            'boq' => 'A',
            'boq_file_path' => UploadedFile::fake()->create('boq-view.pdf', 128, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertOk();

        $phcId = $createResponse->json('data.phc.id');

        $response = $this->actingAs($users['creator'], 'api')->get("/api/phc/{$phcId}/boq-file");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline; filename=', $response->headers->get('content-disposition'));
    }

    public function test_view_phc_boq_file_returns_not_found_when_file_is_missing(): void
    {
        Storage::fake('public');
        Event::fake();

        $users = $this->seedUsers();
        $projectId = $this->seedProject();

        $createResponse = $this->actingAs($users['creator'], 'api')->post('/api/phc', [
            'project_id' => $projectId,
            'pic_marketing_id' => $users['pic_marketing']->id,
            'ho_engineering_id' => $users['ho_engineering']->id,
            'client_pic_name' => 'PIC Client',
            'boq' => 'A',
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertOk();

        $phcId = $createResponse->json('data.phc.id');

        $response = $this->actingAs($users['creator'], 'api')->getJson("/api/phc/{$phcId}/boq-file");

        $response->assertNotFound();
        $response->assertJson([
            'success' => false,
            'message' => 'File BOQ tidak ditemukan',
        ]);
    }

    private function createMinimalSchema(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function ($table) {
                $table->unsignedBigInteger('pn_number')->primary();
                $table->string('project_name');
                $table->string('project_number');
                $table->dateTime('phc_dates')->nullable();
                $table->decimal('po_value', 15, 2)->nullable();
                $table->timestamps();
            });
            $this->createdTables[] = 'projects';
        }

        if (!Schema::hasTable('phcs')) {
            Schema::create('phcs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('ho_marketings_id')->nullable();
                $table->unsignedBigInteger('ho_engineering_id')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->text('notes')->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('target_finish_date')->nullable();
                $table->string('client_pic_name')->nullable();
                $table->string('client_mobile')->nullable();
                $table->string('client_reps_office_address')->nullable();
                $table->string('client_site_address')->nullable();
                $table->string('client_site_representatives')->nullable();
                $table->string('site_phone_number')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('pic_engineering_id')->nullable();
                $table->unsignedBigInteger('pic_marketing_id')->nullable();
                $table->dateTime('handover_date')->nullable();
                $table->boolean('costing_by_marketing')->default(false);
                $table->boolean('boq')->default(false);
                $table->string('boq_file_path')->nullable();
                $table->boolean('retention')->nullable()->default(false);
                $table->boolean('warranty')->nullable()->default(false);
                $table->string('penalty')->nullable();
                $table->decimal('retention_percentage', 5, 2)->nullable();
                $table->integer('retention_months')->nullable();
                $table->date('warranty_date')->nullable();
                $table->timestamps();
            });
            $this->createdTables[] = 'phcs';
        }

        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function ($table) {
                $table->id();
                $table->string('approvable_type');
                $table->unsignedBigInteger('approvable_id');
                $table->unsignedBigInteger('user_id');
                $table->string('type')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('validated_at')->nullable();
                $table->string('pin_hash')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
            $this->createdTables[] = 'approvals';
        }

        if (!Schema::hasTable('retentions')) {
            Schema::create('retentions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->decimal('retention_value', 15, 2)->nullable();
                $table->timestamps();
            });
            $this->createdTables[] = 'retentions';
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
            $this->createdTables[] = 'notifications';
        }
    }

    private function seedUsers(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Test Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdDepartmentIds[] = $departmentId;

        $marketingRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager_marketing',
            'type_role' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdRoleIds[] = $marketingRoleId;

        $engineeringRoleId = DB::table('roles')->insertGetId([
            'name' => 'engineering_director',
            'type_role' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdRoleIds[] = $engineeringRoleId;

        return [
            'creator' => $this->createUser($marketingRoleId, $departmentId, 'boq-creator'),
            'pic_marketing' => $this->createUser($marketingRoleId, $departmentId, 'boq-pic-marketing'),
            'ho_engineering' => $this->createUser($engineeringRoleId, $departmentId, 'boq-ho-engineering'),
        ];
    }

    private function createUser(int $roleId, int $departmentId, string $suffix): User
    {
        $user = User::create([
            'name' => "User {$suffix}",
            'email' => "{$suffix}@example.com",
            'password' => bcrypt('password'),
            'role_id' => $roleId,
            'department_id' => $departmentId,
        ]);
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function seedProject(): int
    {
        $projectId = random_int(90001, 99999);
        $clientId = DB::table('clients')->value('id');
        $categoryId = DB::table('project_categories')->value('id');
        $statusProjectId = DB::table('status_projects')->value('id');
        $userId = DB::table('users')->value('id');
        $quotationNumber = (string) random_int(202600001, 202699999);

        DB::table('quotations')->insert([
            'quotation_number' => $quotationNumber,
            'client_id' => $clientId,
            'no_quotation' => "Q-TEST-{$quotationNumber}",
            'quotation_weeks' => '4',
            'quotation_value' => 100000,
            'client_pic' => 'PIC Test',
            'user_id' => $userId,
            'status' => 'O',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdQuotationNumbers[] = $quotationNumber;

        DB::table('projects')->insert([
            'pn_number' => $projectId,
            'project_name' => 'Project BOQ Test',
            'project_number' => "PN-TEST/{$projectId}",
            'categories_project_id' => $categoryId,
            'quotations_id' => $quotationNumber,
            'status_project_id' => $statusProjectId,
            'client_id' => $clientId,
            'po_value' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdProjectIds[] = $projectId;

        return $projectId;
    }
}
