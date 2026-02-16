# TODO - PackingListApiController Filter Implementation

## Task

Update the `index` method in `PackingListApiController.php` to filter based on `pl_date` with support for:

- Yearly filter
- Monthly filter (year + month)
- Weekly filter (current week)
- Custom date range filter (from_date + to_date)

## Steps

- [x]   1. Analyze existing code and understand the requirements
- [x]   2. Review similar implementations in other controllers (MarketingProjectController, ActivityLogController)
- [x]   3. Create the implementation plan
- [x]   4. Implement the filter logic in PackingListApiController::index()
- [x]   5. Add weekly filter support

## Implementation Details

### Query Parameters

- `year` - Filter by specific year (e.g., 2025)
- `range_type` - Filter type: 'yearly' (default), 'monthly', 'weekly', 'custom'
- `month` - Month for monthly filter (1-12)
- `from_date` - Start date for custom range filter
- `to_date` - End date for custom range filter

### Filter Logic

```php
if ($rangeType === 'monthly' && $monthParam) {
    // Filter by year and month
    $lists->whereYear('pl_date', $year)
          ->whereMonth('pl_date', $monthParam);
} elseif ($rangeType === 'weekly') {
    // Filter by current week
    $lists->whereBetween('pl_date', [now()->startOfWeek(), now()->endOfWeek()]);
} elseif ($rangeType === 'custom' && $fromDate && $toDate) {
    // Filter by custom date range
    $lists->whereBetween('pl_date', [$fromDate, $toDate]);
} else {
    // Default: filter by year only
    $lists->whereYear('pl_date', $year);
}
```
