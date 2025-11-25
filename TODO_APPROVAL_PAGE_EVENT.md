# Approval Page Real-Time Update Implementation

## Summary

Implemented real-time broadcasting event for approval page updates, similar to `DashboardUpdatedEvent.php`. This allows the approval page to automatically refresh when any approval is updated (approved/rejected) without requiring manual page refresh.

## Files Created

### 1. app/Events/ApprovalPageUpdatedEvent.php

-   **Purpose**: Broadcast event that notifies when any approval status changes
-   **Channel**: `approval.page.updated` (public channel)
-   **Event Name**: `approval.page.updated`
-   **Broadcast Data**:
    -   `approval_type`: Type of approval (PHC, WorkOrder, Log)
    -   `approval_id`: ID of the approval record
    -   `status`: Status of the approval (approved, rejected)
    -   `approvable_type`: Type of the approvable model
    -   `approvable_id`: ID of the approvable model
    -   `message`: Human-readable message
    -   `updated_at`: Timestamp in ISO format

## Files Modified

### 1. app/Http/Controllers/API/ApprovallController.php

-   **Added Import**: `use App\Events\ApprovalPageUpdatedEvent;`
-   **Modified Methods**:

    #### a. `updateStatus()` - PHC Approvals

    -   Added event firing after PHC approval status update
    -   Fires `ApprovalPageUpdatedEvent` with PHC details

    #### b. `updateStatusWo()` - Work Order Approvals

    -   Added event firing after Work Order approval status update
    -   Fires `ApprovalPageUpdatedEvent` with WorkOrder details

    #### c. `updateStatusLog()` - Log Approvals

    -   Added event firing after Log approval status update
    -   Fires `ApprovalPageUpdatedEvent` with Log details

## How It Works

1. When a user approves or rejects any approval (PHC, WorkOrder, or Log), the respective controller method is called
2. After the approval status is updated in the database, the `ApprovalPageUpdatedEvent` is fired
3. The event broadcasts to the `approval.page.updated` channel
4. Frontend listeners subscribed to this channel will receive the event and can refresh the approval list automatically

## Frontend Integration (To Be Implemented)

The frontend should listen to the `approval.page.updated` channel using Laravel Echo:

```javascript
// Example frontend code (to be implemented)
Echo.channel("approval.page.updated").listen(".approval.page.updated", (e) => {
    console.log("Approval updated:", e);
    // Refresh approval list
    // Update UI accordingly
});
```

## Testing Checklist

-   [ ] Test PHC approval update broadcasts correctly
-   [ ] Test Work Order approval update broadcasts correctly
-   [ ] Test Log approval update broadcasts correctly
-   [ ] Verify event data structure is correct
-   [ ] Test frontend receives the broadcast event
-   [ ] Verify approval page refreshes automatically
-   [ ] Test with multiple users simultaneously

## Notes

-   The event uses `ShouldBroadcast` interface for real-time broadcasting
-   Uses public channel (no authentication required)
-   Similar pattern to existing `DashboardUpdatedEvent`
-   Does not interfere with existing approval notification events (`PhcApprovalUpdated`, `WorkOrderApprovalUpdated`, `LogApprovalUpdated`)

## Related Files

-   `app/Events/DashboardUpdatedEvent.php` - Reference implementation
-   `app/Events/LogApprovalUpdated.php` - Existing log approval event
-   `app/Events/PhcApprovalUpdated.php` - Existing PHC approval event
-   `app/Events/WorkOrderApprovalUpdated.php` - Existing Work Order approval event
-   `config/broadcasting.php` - Broadcasting configuration
-   `routes/channels.php` - Channel authorization (if needed)

## Date Created

2025-01-XX

## Status

✅ Backend Implementation Complete
⏳ Frontend Integration Pending
