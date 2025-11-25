# ApprovalPageUpdatedEvent Call Implementation

## Summary

Implement calling of ApprovalPageUpdatedEvent when creating PHC, work orders, and logs that require approval.

## Current Status

-   ApprovalPageUpdatedEvent is already implemented and fired in ApprovallController.php when approvals are approved/rejected
-   Need to add firing when approvals are created

## Files to Modify

### 1. app/Http/Controllers/API/Marketing/MarketingPhcApiController.php

-   **Location**: store() method after approvals are created
-   **Action**: Fire ApprovalPageUpdatedEvent for each approval created for PHC

### 2. app/Http/Controllers/API/LogController.php

-   **Location**: store() method after approval is created (when need_response=true)
-   **Location**: update() method after approval is created (when need_response=true)
-   **Action**: Fire ApprovalPageUpdatedEvent for log approval

### 3. app/Http/Controllers/API/Engineer/WorkOrderApiController.php

-   **Status**: Approval creation is commented out, leaving as is

## Implementation Details

For each approval created, fire ApprovalPageUpdatedEvent with:

-   approval_type: 'PHC', 'WorkOrder', or 'Log'
-   approval_id: The ID of the created approval
-   status: 'pending' (since it's newly created)
-   approvable_type: The model class (PHC::class, WorkOrder::class, Log::class)
-   approvable_id: The ID of the PHC/WorkOrder/Log

## Testing Checklist

-   [ ] Test PHC creation fires ApprovalPageUpdatedEvent
-   [ ] Test Log creation with need_response fires ApprovalPageUpdatedEvent
-   [ ] Test Log update with need_response fires ApprovalPageUpdatedEvent
-   [ ] Verify event data structure is correct
-   [ ] Test frontend receives the broadcast event

## Date Created

2025-01-XX

## Status

⏳ Implementation Pending
