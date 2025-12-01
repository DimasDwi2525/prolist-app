<?php

use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\ApprovallController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Engineer\CategorieLogApiController;
use App\Http\Controllers\API\Engineer\DocumentApiController;
use App\Http\Controllers\API\Engineer\DocumentPreparationApiController;
use App\Http\Controllers\API\Engineer\EngineerDashboardApiController;
use App\Http\Controllers\API\Engineer\Dashboard4kEngineerApiController;
use App\Http\Controllers\API\Engineer\EngineerPhcDocumentiApi;
use App\Http\Controllers\API\Engineer\EngineerProjectApiController;
use App\Http\Controllers\API\Engineer\ManPowerAllocationApiController;
use App\Http\Controllers\API\Engineer\ManPowerDashboardApiController;
use App\Http\Controllers\API\Engineer\ManPowerProjectApiController;
use App\Http\Controllers\API\Engineer\OutstandingProjectApiController;
use App\Http\Controllers\API\Engineer\ProjectFinishedSummaryApiController;
use App\Http\Controllers\API\Engineer\PurposeWorkOrderApiController;
use App\Http\Controllers\API\Engineer\ScopeOfWorkProjectApiController;
use App\Http\Controllers\API\Engineer\ManPower\WorkOrderManPowerApiController;
use App\Http\Controllers\API\Engineer\WorkOrderApiController;
use App\Http\Controllers\API\Engineer\WorkOrderSummaryApiController;
use App\Http\Controllers\API\LogController;
use App\Http\Controllers\API\Marketing\BillOfQuantityController;
use App\Http\Controllers\API\Marketing\MarketingCategorieProject;
use App\Http\Controllers\API\Marketing\MarketingClientController;
use App\Http\Controllers\API\Marketing\MarketingDashboardController;
use App\Http\Controllers\API\Marketing\MarketingPhcApiController;
use App\Http\Controllers\API\Marketing\MarketingProjectController;
use App\Http\Controllers\API\Marketing\MarketingQuotationController;
use App\Http\Controllers\API\Marketing\MarketingReportApiController;
use App\Http\Controllers\API\Marketing\MarketingStatusProjectController;
use App\Http\Controllers\API\Marketing\SalesReportApiController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ProfileApiController;
use App\Http\Controllers\API\SUC\MaterialRequestApiController;
use App\Http\Controllers\API\SUC\PackingListApiController;
use App\Http\Controllers\API\SUC\SUCDashboardController;
use App\Http\Controllers\API\SUC\MasterTypePackingListApiController;
use App\Http\Controllers\API\SUC\MasterExpeditionApiController;
use App\Http\Controllers\API\SUC\DestinationApiController;
use App\Http\Controllers\API\users\DepartmentController;
use App\Http\Controllers\API\users\RoleController;
use App\Http\Controllers\API\users\UsersController;
use App\Http\Controllers\API\Finance\InvoiceTypeController;
use App\Http\Controllers\API\Finance\InvoicePaymentController;
use App\Http\Controllers\API\Finance\RequestInvoiceApiController;
use App\Http\Controllers\API\Finance\RequestInvoiceListApiController;
use App\Http\Controllers\API\Finance\FinanceDashboardController;
use App\Http\Controllers\API\Finance\TaxController;
use App\Http\Controllers\API\Finance\HoldingTaxController;
use App\Http\Controllers\API\Finance\RetentionController;
use App\Http\Controllers\API\Finance\DeliveryOrderController;
use App\Http\Controllers\API\MasterStatusMrController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (SPA Sanctum)
|--------------------------------------------------------------------------
| Semua request dari React harus include withCredentials:true
| supaya cookie session dan CSRF token ikut terkirim.
|--------------------------------------------------------------------------
*/
Broadcast::routes(['middleware' => ['auth:api']]);
// Login & CSRF cookie
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'me']);


// Semua route berikut butuh auth:sanctum
Route::middleware('auth:api')->group(function () {

    Route::get('/engineer/dashboard', [EngineerDashboardApiController::class, 'index']);
    Route::get('/engineer/dashboard4k', [Dashboard4kEngineerApiController::class, 'index']);
    Route::get('/projects/finished-summary', [ProjectFinishedSummaryApiController::class, 'finishedSummary']);
    Route::get('/work-order/work-order-summary', [WorkOrderSummaryApiController::class, 'workOrderSummary']);
    Route::post('/users/{user}/upload-photo', [UsersController::class, 'uploadPhoto']);

    // Cek profil user
    Route::get('/account/profile', [ProfileApiController::class, 'profile']);

    // Update password
    Route::post('/account/password', [ProfileApiController::class, 'updatePassword']);

    // Update PIN
    Route::post('/account/pin', [ProfileApiController::class, 'updatePin']);

    Route::get('/users', [UsersController::class, 'index']);
    Route::post('/users', [UsersController::class, 'store']);
    Route::get('/users/engineer-only', [UsersController::class, 'engineerOnly']);
    Route::get('/users/roleTypeTwoOnly', [UsersController::class, 'roleTypeTwoOnly']);
    Route::get('/users/manPowerUsers', [UsersController::class, 'manPowerUsers']);
    Route::get('/users/manPowerRoles', [UsersController::class, 'manPowerRoles']);
    Route::get('/users/{user}', [UsersController::class, 'show']);
    Route::put('/users/{user}', [UsersController::class, 'update']);
    Route::delete('/users/{user}', [UsersController::class, 'destroy']);

    // Roles
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/onlyType1',[RoleController::class, 'roleOnlyType1']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    Route::get('/phc/users/engineering', [UsersController::class, 'engineeringUsers']);
    Route::get('/phc/users/marketing', [UsersController::class, 'marketingUsers']);
    

    
    
    // List semua approval user
    Route::get('approvals', [ApprovallController::class, 'index']);
    // Detail approval
    Route::get('approvals/{id}', [ApprovallController::class, 'show']);

    Route::post('approvals/log/{id}/status', [ApprovallController::class, 'updateStatusLog']);
    Route::post('approvals/wo/{id}/status', [ApprovallController::class, 'updateStatusWo']);
    Route::post('approvals/{id}/status', [ApprovallController::class, 'updateStatus']); 


    

    Route::get('/phc/{id}', [MarketingPhcApiController::class, 'show']);

    

    
    // Route::middleware('role:super_admin,marketing_director,engineering_director,supervisor marketing,manager_marketing,sales_supervisor,marketing_admin,marketing_estimator')
    //     ->group(function () {
            
    //     });

    Route::get('/marketing', [MarketingDashboardController::class, 'index']);

    // Client CRUD
    Route::get('/clients', [MarketingClientController::class, 'index']);
    Route::post('/clients', [MarketingClientController::class, 'store']);
    Route::put('/clients/{client}', [MarketingClientController::class, 'update']);
    Route::delete('/clients/{client}', [MarketingClientController::class, 'destroy']);

    //quotation CRUD
    Route::get('/quotations', [MarketingQuotationController::class, 'index']);
    Route::get('/quotations/next-number', [MarketingQuotationController::class, 'nextNumber']);
    Route::get('/quotations/{quotation}', [MarketingQuotationController::class, 'show']);
    Route::post('/quotations', [MarketingQuotationController::class, 'store']);
    Route::put('/quotations/{quotation}', [MarketingQuotationController::class, 'update']);
    Route::delete('/quotations/{quotation}', [MarketingQuotationController::class, 'destroy']);

    Route::get('/categories-project', [MarketingCategorieProject::class, 'index']);   // GET all
    Route::post('/categories-project', [MarketingCategorieProject::class, 'store']); // POST create
    Route::get('/categories-project/{category}', [MarketingCategorieProject::class, 'show']); // GET detail
    Route::put('/categories-project/{category}', [MarketingCategorieProject::class, 'update']); // PUT update
    Route::delete('/categories-project/{category}', [MarketingCategorieProject::class, 'destroy']); // DELETE

    Route::get('/status-projects', [MarketingStatusProjectController::class, 'index']);
    Route::post('/status-projects', [MarketingStatusProjectController::class, 'store']);
    Route::get('/status-projects/{statusProject}', [MarketingStatusProjectController::class, 'show']);
    Route::put('/status-projects/{statusProject}', [MarketingStatusProjectController::class, 'update']);
    Route::delete('/status-projects/{statusProject}', [MarketingStatusProjectController::class, 'destroy']);

    Route::get('/sales-report', [SalesReportApiController::class, 'index']);
    Route::get('/marketing-report', [MarketingReportApiController::class, 'index']);

    Route::get('/projects/generate-number', [MarketingProjectController::class, 'generateNumber']);
    
    Route::post('/projects', [MarketingProjectController::class, 'store']);
    
    Route::put('/projects/{project}', [MarketingProjectController::class, 'update']);
    Route::delete('/projects/{project}', [MarketingProjectController::class, 'destroy']);

    Route::post('/phc', [MarketingPhcApiController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/all', [NotificationController::class, 'all']);
    Route::get('/notifications/count', [NotificationController::class, 'count']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    

    Route::get('/projects/{projectId}/boq', [BillOfQuantityController::class, 'index']);
    Route::post('/projects/{projectId}/boq', [BillOfQuantityController::class, 'store']);
    Route::put('/boq/{id}', [BillOfQuantityController::class, 'update']);

    
    Route::put('/phc/{id}', [MarketingPhcApiController::class, 'update']);


    Route::get('/ajax-clients', [MarketingQuotationController::class, 'ajaxClients']);

    // Route::middleware('role:super_admin,marketing_director,engineering_director,supervisor marketing,manager_marketing,sales_supervisor,marketing_admin,marketing_estimator,project controller,project manager,warehouse')
    // ->group(function () {

        
    // });
    // Client CRUD
    Route::get('/clients', [MarketingClientController::class, 'index']);
    Route::get('/quotations', [MarketingQuotationController::class, 'index']);

    Route::get('/categories-project', [MarketingCategorieProject::class, 'index']);   // GET all

    Route::get('/status-projects', [MarketingStatusProjectController::class, 'index']);

    Route::get('/projects', [MarketingProjectController::class, 'index']);
    Route::get('/projects/{project}', [MarketingProjectController::class, 'show']);

    Route::get('/phcs/show/{phc}', [EngineerPhcDocumentiApi::class, 'show']);
    
    Route::get('/document-preparations/{documentPreparationId}/attachment', [EngineerPhcDocumentiApi::class, 'viewAttachment']);
    Route::post('/document-preparations/{documentPreparationId}/upload', [EngineerPhcDocumentiApi::class, 'uploadDocument']);
    Route::get('/phcs/{phc}/document-preparations', [DocumentPreparationApiController::class, 'index']);

    Route::get('/document-phc', [DocumentApiController::class, 'index']);

    // Route::middleware('role:super_admin,marketing_director,engineering_director,project controller,project manager')
    // ->group(function () {
        
    // });

    Route::put('/phcs/{phc}', [EngineerPhcDocumentiApi::class, 'update']);
                
    Route::get('/document-phc/{id}', [DocumentApiController::class, 'show']); 
    Route::post('/document-phc', [DocumentApiController::class, 'store']);    
    Route::put('/document-phc/{id}', [DocumentApiController::class, 'update']); 
    Route::delete('/document-phc/{id}', [DocumentApiController::class, 'destroy']); 

    Route::put('/phcs/{phc}', [EngineerPhcDocumentiApi::class, 'update']);
    

    Route::get('/document-phc', [DocumentApiController::class, 'index']);
    Route::get('/document-phc/{id}', [DocumentApiController::class, 'show']);
    Route::post('/document-phc', [DocumentApiController::class, 'store']);
    Route::put('/document-phc/{id}', [DocumentApiController::class, 'update']);
    Route::delete('/document-phc/{id}', [DocumentApiController::class, 'destroy']);

    Route::get('/material-requests', [MaterialRequestApiController::class, 'index']);
    Route::post('/material-requests', [MaterialRequestApiController::class, 'store']);

    Route::get('/material-requests/get-available-statuses', [MaterialRequestApiController::class, 'getAvailableStatuses']);

    Route::get('/material-requests/{materialRequest}', [MaterialRequestApiController::class, 'show']);
    Route::put('/material-requests/{materialRequest}', [MaterialRequestApiController::class, 'update']);
    Route::patch('/material-requests/{materialRequest}', [MaterialRequestApiController::class, 'update']);
    Route::delete('/material-requests/{materialRequest}', [MaterialRequestApiController::class, 'destroy']);

    Route::post('/{materialRequest}/cancel', [MaterialRequestApiController::class, 'cancel']);
    Route::post('/{materialRequest}/handover', [MaterialRequestApiController::class, 'handover']);

    Route::get('/mr-summary', [MaterialRequestApiController::class, 'getMrSummary']);

    Route::get('/work-order/{pn_number}', [WorkOrderApiController::class, 'index']); // GET by PN
    Route::get('/work-orders/next-code', [WorkOrderApiController::class, 'nextCode']);
    Route::get('/work-order/detail/{id}', [WorkOrderApiController::class, 'show']);
    Route::get('/work-orders/{id}/pdf', [WorkOrderApiController::class, 'downloadPdf']);
    Route::post('/work-order', [WorkOrderApiController::class, 'store']);           // CREATE
    Route::put('/work-order/{id}', [WorkOrderApiController::class, 'update']);       // UPDATE
    Route::get('/wo-summary', [WorkOrderApiController::class, 'getWoSummary']);
    
    
    Route::get('/man-power/dashboard', [ManPowerDashboardApiController::class, 'index']);
    Route::get('/man-power/projects', [ManPowerProjectApiController::class, 'index']);

    // Work Orders for Man Power
    Route::get('/man-power/work-orders/{pn_number}', [WorkOrderManPowerApiController::class, 'index']);
    Route::post('/man-power/work-orders', [WorkOrderManPowerApiController::class, 'store']);
    Route::get('/man-power/work-orders/{id}', [WorkOrderManPowerApiController::class, 'show']);
    Route::put('/man-power/work-orders/{id}', [WorkOrderManPowerApiController::class, 'update']);
    Route::get('/man-power/work-orders/{id}/pdf', [WorkOrderManPowerApiController::class, 'downloadPdf']);
    Route::get('/man-power/work-orders-summary', [WorkOrderManPowerApiController::class, 'getWoSummary']);

    // List all allocations by project PN number
    Route::get('man-power/{pn_number}', [ManPowerAllocationApiController::class, 'index']);

    // Show single allocation
    Route::get('man-power/show/{id}', [ManPowerAllocationApiController::class, 'show']);

    // Create new allocation
    Route::post('man-power', [ManPowerAllocationApiController::class, 'store']);

    // Update allocation
    Route::put('man-power/{id}', [ManPowerAllocationApiController::class, 'update']);

    // Delete allocation
    Route::delete('man-power/{id}', [ManPowerAllocationApiController::class, 'destroy']);

    Route::get('/packing-lists/generate-number', [PackingListApiController::class, 'generateNumber']);
    Route::get('/packing-lists', [PackingListApiController::class, 'index']);

    Route::post('/packing-lists', [PackingListApiController::class, 'store']);
    Route::get('/packing-lists/{id}', [PackingListApiController::class, 'show']);
    Route::put('/packing-lists/{id}', [PackingListApiController::class, 'update']);
    Route::delete('/packing-lists/{id}', [PackingListApiController::class, 'destroy']);
    Route::post('/packing-lists/{id}/create-delivery-order', [PackingListApiController::class, 'createDeliveryOrder']);
    Route::get('/packing-lists/{id}/confirm-delivery-order', [PackingListApiController::class, 'confirmDeliveryOrder']);

    // SUC Dashboard routes
    Route::get('/suc/dashboard', [SUCDashboardController::class, 'index']);

    Route::get('/projects/{project}/logs', [LogController::class, 'index']);
    Route::get('/logs/{id}', [LogController::class, 'show']);
    Route::post('/logs', [LogController::class, 'store']);
    Route::put('/logs/{id}', [LogController::class, 'update']);
    Route::delete('/logs/{id}', [LogController::class, 'destroy']);
    Route::patch('/logs/{id}/close', [LogController::class, 'close']);

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);
    Route::get('/activity-logs/actions/list', [ActivityLogController::class, 'getActions']);
    Route::get('/activity-logs/model-types/list', [ActivityLogController::class, 'getModelTypes']);

    // Tampilkan semua kategori
    Route::get('/categories-log', [CategorieLogApiController::class, 'index']);

    Route::get('/categories-log/{id}', [CategorieLogApiController::class, 'show']);

    Route::post('/categories-log', [CategorieLogApiController::class, 'store']);

    Route::put('/categories-log/{id}', [CategorieLogApiController::class, 'update']);

    Route::delete('/categories-log/{id}', [CategorieLogApiController::class, 'destroy']);

    

    Route::get('/scope-of-work', [ScopeOfWorkProjectApiController::class, 'index']);
    Route::post('/scope-of-work', [ScopeOfWorkProjectApiController::class, 'store']);
    Route::get('/scope-of-work/{id}', [ScopeOfWorkProjectApiController::class, 'show']);
    Route::put('/scope-of-work/{id}', [ScopeOfWorkProjectApiController::class, 'update']);
    Route::delete('/scope-of-work/{id}', [ScopeOfWorkProjectApiController::class, 'destroy']);

    Route::get('/purpose-work-orders', [PurposeWorkOrderApiController::class, 'index']);
    Route::get('/purpose-work-orders/{id}', [PurposeWorkOrderApiController::class, 'show']);
    Route::post('/purpose-work-orders', [PurposeWorkOrderApiController::class, 'store']);
    Route::put('/purpose-work-orders/{id}', [PurposeWorkOrderApiController::class, 'update']);
    Route::delete('/purpose-work-orders/{id}', [PurposeWorkOrderApiController::class, 'destroy']);

    Route::get('/project/man-power', [EngineerProjectApiController::class, 'engineerProjects']);

    Route::get('/outstanding-projects', [OutstandingProjectApiController::class, 'index']);

    Route::post('engineer/projects/{pn_number}/status', [EngineerProjectApiController::class, 'updateStatus']);

    // Finance routes
    Route::prefix('finance')->group(function () {
        Route::get('dashboard', [FinanceDashboardController::class, 'index']);

        Route::get('invoice-types', [InvoiceTypeController::class, 'index']);
        Route::post('invoice-types', [InvoiceTypeController::class, 'store']);
        Route::get('invoice-types/{id}', [InvoiceTypeController::class, 'show']);
        Route::put('invoice-types/{id}', [InvoiceTypeController::class, 'update']);
        Route::delete('invoice-types/{id}', [InvoiceTypeController::class, 'destroy']);

        Route::get('invoices', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'index']);
        Route::get('invoice-list', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'invoiceList']);
        Route::post('invoices', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'store']);
        Route::get('invoices/next-id', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'nextInvoiceId']);
        Route::get('invoices/validate-sequence', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'validateSequence']);
        Route::get('invoice-summary', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'invoiceSummary']);
        Route::get('invoices/validate', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'validateInvoice']);
        Route::get('invoices/preview-taxes', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'previewTaxes']);
        Route::get('invoices/{id}', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'show']);
        Route::put('invoices/{id}', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'update']);
        Route::delete('invoices/{id}', [\App\Http\Controllers\API\Finance\InvoiceController::class, 'destroy']);


        Route::get('invoice-payments', [InvoicePaymentController::class, 'index']);
        Route::post('invoice-payments', [InvoicePaymentController::class, 'store']);
        Route::get('invoice-payments/validate', [InvoicePaymentController::class, 'validatePayment']);
        Route::get('invoice-payments/{id}', [InvoicePaymentController::class, 'show']);
        Route::put('invoice-payments/{id}', [InvoicePaymentController::class, 'update']);
        Route::delete('invoice-payments/{id}', [InvoicePaymentController::class, 'destroy']);

        Route::get('taxes', [TaxController::class, 'index']);
        Route::post('taxes', [TaxController::class, 'store']);
        Route::get('taxes/{id}', [TaxController::class, 'show']);
        Route::put('taxes/{id}', [TaxController::class, 'update']);
        Route::delete('taxes/{id}', [TaxController::class, 'destroy']);

        Route::get('holding-taxes/invoice', [HoldingTaxController::class, 'getByInvoiceId']);
        Route::put('holding-taxes/invoice', [HoldingTaxController::class, 'update']);

        Route::get('retentions', [RetentionController::class, 'index']);
        Route::get('retentions/{id}', [RetentionController::class, 'show']);
        Route::put('retentions/{id}', [RetentionController::class, 'update']);
        Route::delete('retentions/{id}', [RetentionController::class, 'destroy']);

        Route::get('delivery-orders', [DeliveryOrderController::class, 'index']);
        Route::post('delivery-orders', [DeliveryOrderController::class, 'store']);
        Route::get('delivery-orders/{id}', [DeliveryOrderController::class, 'show']);
        Route::put('delivery-orders/{id}', [DeliveryOrderController::class, 'update']);
        Route::delete('delivery-orders/{id}', [DeliveryOrderController::class, 'destroy']);
    });

    Route::get('request-invoices-summary', [RequestInvoiceApiController::class, 'summary']);
    Route::get('request-invoices-list', [RequestInvoiceListApiController::class, 'index']);
    Route::get('request-invoices-list/{id}', [RequestInvoiceListApiController::class, 'show']);
    Route::post('request-invoices-list/{id}/approve', [RequestInvoiceListApiController::class, 'approve']);
    Route::get('request-invoices/{pn_number}', [RequestInvoiceApiController::class, 'index']);
    Route::get('request-invoices/{pn_number}/phc-documents', [RequestInvoiceApiController::class, 'getPhcDocuments']);
    Route::post('request-invoices', [RequestInvoiceApiController::class, 'store']);
    Route::get('request-invoices/show/{id}', [RequestInvoiceApiController::class, 'show']);
    Route::put('request-invoices/{id}', [RequestInvoiceApiController::class, 'update']);

    // Master Status MR routes
    Route::get('master-status-mr', [MasterStatusMrController::class, 'index']);
    Route::post('master-status-mr', [MasterStatusMrController::class, 'store']);
    Route::get('master-status-mr/{id}', [MasterStatusMrController::class, 'show']);
    Route::put('master-status-mr/{id}', [MasterStatusMrController::class, 'update']);
    Route::delete('master-status-mr/{id}', [MasterStatusMrController::class, 'destroy']);

    // Master Type Packing Lists routes
    Route::get('master-type-packing-lists', [MasterTypePackingListApiController::class, 'index']);
    Route::post('master-type-packing-lists', [MasterTypePackingListApiController::class, 'store']);
    Route::get('master-type-packing-lists/{id}', [MasterTypePackingListApiController::class, 'show']);
    Route::put('master-type-packing-lists/{id}', [MasterTypePackingListApiController::class, 'update']);
    Route::delete('master-type-packing-lists/{id}', [MasterTypePackingListApiController::class, 'destroy']);

    // Master Expeditions routes
    Route::get('master-expeditions', [MasterExpeditionApiController::class, 'index']);
    Route::post('master-expeditions', [MasterExpeditionApiController::class, 'store']);
    Route::get('master-expeditions/{id}', [MasterExpeditionApiController::class, 'show']);
    Route::put('master-expeditions/{id}', [MasterExpeditionApiController::class, 'update']);
    Route::delete('master-expeditions/{id}', [MasterExpeditionApiController::class, 'destroy']);

    // Destinations routes
    Route::get('destinations', [DestinationApiController::class, 'index']);
    Route::post('destinations', [DestinationApiController::class, 'store']);
    Route::get('destinations/{id}', [DestinationApiController::class, 'show']);
    Route::put('destinations/{id}', [DestinationApiController::class, 'update']);
    Route::delete('destinations/{id}', [DestinationApiController::class, 'destroy']);

});
