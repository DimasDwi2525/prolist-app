<?php

namespace Tests\Feature;

use App\Http\Controllers\API\Engineer\ManPowerAllocationApiController;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ManPowerAllocationEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_update_endpoint_routes_to_update_controller_method(): void
    {
        $this->withoutMiddleware();

        $controller = Mockery::mock(ManPowerAllocationApiController::class)->makePartial();
        $controller->shouldReceive('callAction')->passthru();
        $controller->shouldReceive('update')
            ->once()
            ->with(Mockery::type(Request::class), '10')
            ->andReturn(response()->json([
                'success' => true,
                'data' => [
                    'id' => 10,
                    'project_id' => 25001,
                    'user_id' => 7,
                    'role_id' => 3,
                ],
            ]));

        $this->app->instance(ManPowerAllocationApiController::class, $controller);

        $response = $this->putJson('/api/man-power/10', [
            'project_id' => 25001,
            'user_id' => 7,
            'role_id' => 3,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 10);
    }

    public function test_delete_endpoint_routes_to_destroy_controller_method(): void
    {
        $this->withoutMiddleware();

        $controller = Mockery::mock(ManPowerAllocationApiController::class)->makePartial();
        $controller->shouldReceive('callAction')->passthru();
        $controller->shouldReceive('destroy')
            ->once()
            ->with('10')
            ->andReturn(response()->json([
                'success' => true,
                'message' => 'Allocation deleted successfully',
            ]));

        $this->app->instance(ManPowerAllocationApiController::class, $controller);

        $response = $this->deleteJson('/api/man-power/10');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Allocation deleted successfully');
    }
}
