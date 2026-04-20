<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceApiDocumentationTest extends TestCase
{
    #[DataProvider('financeEndpointsProvider')]
    public function test_finance_api_route_is_registered_and_documented(string $method, string $uri): void
    {
        $this->assertRouteIsRegistered($method, $uri);

        $documentation = file_get_contents(base_path('docs/finance-api.md'));

        $this->assertStringContainsString(
            "{$method} /api/{$uri}",
            $documentation,
            "Endpoint {$method} /api/{$uri} belum ada di docs/finance-api.md"
        );
    }

    public static function financeEndpointsProvider(): array
    {
        return [
            ['GET', 'finance/dashboard'],
            ['GET', 'finance/invoice-types'],
            ['POST', 'finance/invoice-types'],
            ['GET', 'finance/invoice-types/{id}'],
            ['PUT', 'finance/invoice-types/{id}'],
            ['DELETE', 'finance/invoice-types/{id}'],
            ['GET', 'finance/invoices'],
            ['GET', 'finance/invoice-list'],
            ['POST', 'finance/invoices'],
            ['GET', 'finance/invoices/next-id'],
            ['GET', 'finance/invoices/validate-sequence'],
            ['GET', 'finance/invoice-summary'],
            ['GET', 'finance/invoices/validate'],
            ['GET', 'finance/invoices/preview-taxes'],
            ['GET', 'finance/invoices/{id}'],
            ['PUT', 'finance/invoices/{id}'],
            ['DELETE', 'finance/invoices/{id}'],
            ['GET', 'finance/invoice-payments'],
            ['POST', 'finance/invoice-payments'],
            ['GET', 'finance/invoice-payments/validate'],
            ['GET', 'finance/invoice-payments/{id}'],
            ['PUT', 'finance/invoice-payments/{id}'],
            ['DELETE', 'finance/invoice-payments/{id}'],
            ['GET', 'finance/taxes'],
            ['POST', 'finance/taxes'],
            ['GET', 'finance/taxes/{id}'],
            ['PUT', 'finance/taxes/{id}'],
            ['DELETE', 'finance/taxes/{id}'],
            ['GET', 'finance/holding-taxes/invoice'],
            ['PUT', 'finance/holding-taxes/invoice'],
            ['GET', 'finance/retentions'],
            ['GET', 'finance/retentions/{id}'],
            ['PUT', 'finance/retentions/{id}'],
            ['DELETE', 'finance/retentions/{id}'],
            ['GET', 'finance/delivery-orders'],
            ['POST', 'finance/delivery-orders'],
            ['GET', 'finance/delivery-orders/{id}'],
            ['PUT', 'finance/delivery-orders/{id}'],
            ['DELETE', 'finance/delivery-orders/{id}'],
            ['GET', 'request-invoices-summary'],
            ['GET', 'request-invoices-list'],
            ['GET', 'request-invoices-list/{id}'],
            ['POST', 'request-invoices-list/{id}/approve'],
            ['GET', 'request-invoices/{pn_number}'],
            ['GET', 'request-invoices/{pn_number}/phc-documents'],
            ['POST', 'request-invoices'],
            ['GET', 'request-invoices/show/{id}'],
            ['PUT', 'request-invoices/{id}'],
        ];
    }

    #[DataProvider('invoiceRoutesWithSlashProvider')]
    public function test_invoice_routes_allow_slashes_in_last_parameter(string $method): void
    {
        $request = request()->create('/api/finance/invoices/IP/26/0001', $method);
        $route = Route::getRoutes()->match($request);

        $this->assertSame('api/finance/invoices/{id}', $route->uri());
        $this->assertSame('IP/26/0001', $route->parameter('id'));
    }

    public static function invoiceRoutesWithSlashProvider(): array
    {
        return [
            ['GET'],
            ['PUT'],
            ['DELETE'],
        ];
    }

    private function assertRouteIsRegistered(string $method, string $uri): void
    {
        $routeUri = "api/{$uri}";

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $routeUri && in_array($method, $route->methods(), true)) {
                $this->assertContains('auth:api', $route->gatherMiddleware());
                return;
            }
        }

        $this->fail("Route {$method} {$uri} tidak terdaftar.");
    }
}
