<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Department;
use App\Models\PHC;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhcApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_phc_flow_when_ho_engineering_is_null(): void
    {
        $marketingRole = $this->createRole('manager_marketing');
        $pmRole = $this->createRole('project manager');
        $pcRole = $this->createRole('project controller');
        $department = $this->createDepartment();

        $creator = $this->createUser($marketingRole, $department, 'creator');
        $picMarketing = $this->createUser($marketingRole, $department, 'pic-marketing');
        $pm1 = $this->createUser($pmRole, $department, 'pm-1', '123456');
        $pm2 = $this->createUser($pmRole, $department, 'pm-2');
        $pc1 = $this->createUser($pcRole, $department, 'pc-1');

        $project = $this->createProject($creator);

        $createResponse = $this->actingAs($creator, 'api')->postJson('/api/phc', [
            'project_id' => $project->pn_number,
            'pic_marketing_id' => $picMarketing->id,
            'ho_engineering_id' => null,
            'client_pic_name' => 'PIC Client',
        ]);

        $createResponse->assertStatus(200);

        $phcId = $createResponse->json('data.phc.id');
        $this->assertNotNull($phcId);

        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $picMarketing->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pm1->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pm2->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pc1->id,
            'status' => 'pending',
        ]);

        $this->assertSame(4, Approval::where('approvable_type', PHC::class)->where('approvable_id', $phcId)->count());

        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $picMarketing->id));
        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $pm1->id));
        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $pm2->id));
        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $pc1->id));

        $pm1ApprovalId = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phcId)
            ->where('user_id', $pm1->id)
            ->value('id');

        $approvePmResponse = $this->actingAs($pm1, 'api')->postJson("/api/approvals/{$pm1ApprovalId}/status", [
            'status' => 'approved',
            'pin' => '123456',
        ]);

        $approvePmResponse->assertStatus(200);

        $phc = PHC::findOrFail($phcId);
        $this->assertSame($pm1->id, $phc->ho_engineering_id);
        $this->assertSame(PHC::STATUS_WAITING_APPROVAL, $phc->status);

        $this->assertDatabaseMissing('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pm2->id,
        ]);
        $this->assertDatabaseMissing('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pc1->id,
        ]);

        $this->assertSame(0, $this->phcValidationNotificationCount($phcId, $pm2->id));
        $this->assertSame(0, $this->phcValidationNotificationCount($phcId, $pc1->id));

        $picApprovalId = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phcId)
            ->where('user_id', $picMarketing->id)
            ->value('id');

        $approvePicResponse = $this->actingAs($picMarketing, 'api')->postJson("/api/approvals/{$picApprovalId}/status", [
            'status' => 'approved',
            'pin' => '123456',
        ]);

        $approvePicResponse->assertStatus(200);

        $phc->refresh();
        $this->assertSame(PHC::STATUS_APPROVED, $phc->status);
    }

    public function test_phc_flow_when_ho_engineering_is_specified(): void
    {
        $marketingRole = $this->createRole('manager_marketing');
        $pmRole = $this->createRole('project manager');
        $pcRole = $this->createRole('project controller');
        $engineeringDirectorRole = $this->createRole('engineering_director');
        $department = $this->createDepartment();

        $creator = $this->createUser($marketingRole, $department, 'creator2');
        $picMarketing = $this->createUser($marketingRole, $department, 'pic-marketing2');
        $hoEngineering = $this->createUser($engineeringDirectorRole, $department, 'ho-eng', '123456');
        $pmFallback = $this->createUser($pmRole, $department, 'pm-fallback');
        $pcFallback = $this->createUser($pcRole, $department, 'pc-fallback');

        $project = $this->createProject($creator);

        $createResponse = $this->actingAs($creator, 'api')->postJson('/api/phc', [
            'project_id' => $project->pn_number,
            'pic_marketing_id' => $picMarketing->id,
            'ho_engineering_id' => $hoEngineering->id,
            'client_pic_name' => 'PIC Client',
        ]);

        $createResponse->assertStatus(200);
        $phcId = $createResponse->json('data.phc.id');

        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $picMarketing->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $hoEngineering->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pmFallback->id,
        ]);
        $this->assertDatabaseMissing('approvals', [
            'approvable_type' => PHC::class,
            'approvable_id' => $phcId,
            'user_id' => $pcFallback->id,
        ]);

        $this->assertSame(2, Approval::where('approvable_type', PHC::class)->where('approvable_id', $phcId)->count());

        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $picMarketing->id));
        $this->assertSame(1, $this->phcValidationNotificationCount($phcId, $hoEngineering->id));
        $this->assertSame(0, $this->phcValidationNotificationCount($phcId, $pmFallback->id));
        $this->assertSame(0, $this->phcValidationNotificationCount($phcId, $pcFallback->id));

        $picApprovalId = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phcId)
            ->where('user_id', $picMarketing->id)
            ->value('id');
        $hoApprovalId = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phcId)
            ->where('user_id', $hoEngineering->id)
            ->value('id');

        $this->actingAs($picMarketing, 'api')->postJson("/api/approvals/{$picApprovalId}/status", [
            'status' => 'approved',
            'pin' => '123456',
        ])->assertStatus(200);

        $phc = PHC::findOrFail($phcId);
        $this->assertSame(PHC::STATUS_WAITING_APPROVAL, $phc->status);
        $this->assertSame($hoEngineering->id, $phc->ho_engineering_id);

        $this->actingAs($hoEngineering, 'api')->postJson("/api/approvals/{$hoApprovalId}/status", [
            'status' => 'approved',
            'pin' => '123456',
        ])->assertStatus(200);

        $phc->refresh();
        $this->assertSame(PHC::STATUS_APPROVED, $phc->status);
        $this->assertSame($hoEngineering->id, $phc->ho_engineering_id);
    }

    private function createRole(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'type_role' => 1,
        ]);
    }

    private function createDepartment(): Department
    {
        return Department::create([
            'name' => 'Dept Test',
        ]);
    }

    private function createUser(Role $role, Department $department, string $suffix, string $pin = '123456'): User
    {
        return User::create([
            'name' => "User {$suffix}",
            'email' => "{$suffix}@example.com",
            'password' => bcrypt('password'),
            'pin' => $pin,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);
    }

    private function createProject(User $quotationUser): Project
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Client Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statusProjectId = DB::table('status_projects')->insertGetId([
            'name' => 'On Progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('project_categories')->insertGetId([
            'name' => 'Category Test',
            'description' => 'Desc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quotationNumber = 'QT-' . now()->format('YmdHisv') . '-' . random_int(100, 999);
        DB::table('quotations')->insert([
            'quotation_number' => $quotationNumber,
            'client_id' => $clientId,
            'no_quotation' => 'NO-' . $quotationNumber,
            'quotation_weeks' => '4',
            'quotation_value' => 100000,
            'client_pic' => 'PIC Client',
            'user_id' => $quotationUser->id,
            'status' => 'O',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Project::create([
            'project_name' => 'Project Test',
            'categories_project_id' => $categoryId,
            'quotations_id' => $quotationNumber,
            'status_project_id' => $statusProjectId,
            'client_id' => $clientId,
            'po_date' => now()->toDateString(),
        ]);
    }

    private function phcValidationNotificationCount(int $phcId, int $userId): int
    {
        return DB::table('notifications')
            ->where('type', \App\Notifications\PhcValidationRequested::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('data', 'like', '%"phc_id":' . $phcId . '%')
            ->count();
    }
}
