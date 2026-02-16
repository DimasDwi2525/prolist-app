# TODO - Marketing & Sales Report PDF Implementation

## Step 1: Create PDF Library Class

-   [x] Create `app/Libraries/MarketingSalesReportPdf.php`
    -   [x] Yellow background header with title
    -   [x] 3-column header info layout (left, center, right)
    -   [x] Data table with columns: No, Sales, Marketing & Sales
    -   [x] Sales sub-columns: inquiry_date, project_number, client, client_pic, contact_person, project_name
    -   [x] Marketing & Sales sub-columns: quotation_no, status, po_no, po_value

## Step 2: Add PDF Method to Controller

-   [x] Update `app/Http/Controllers/API/Marketing/SalesReportApiController.php`
    -   [x] Add `downloadPdf(Request $request)` method
    -   [x] Reuse existing filtering logic from index() method
    -   [x] Generate PDF and return as download response

## Step 3: Add Route

-   [x] Update `routes/api.php`
    -   [x] Add PDF download route for sales report

## Step 4: Testing

-   [ ] Verify PDF generation works correctly
-   [ ] Test with different filters (year, monthly, weekly, custom)
