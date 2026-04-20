# Dokumentasi API Finance

Base URL: `/api`

Autentikasi: semua endpoint di dokumen ini berada di dalam middleware `auth:api`. Client harus mengirim token/session API sesuai mekanisme autentikasi aplikasi.

Format response umum: JSON. Response validasi Laravel menggunakan status `422`. Data yang tidak ditemukan dari `findOrFail()` akan mengembalikan status `404`.

## Dashboard Finance

### `GET /api/finance/dashboard`

Mengambil ringkasan dashboard finance.

Response utama:

```json
{
  "request_invoice": 3,
  "request_invoice_list": [],
  "jumlah_pn": 12,
  "total_delivery_order": 5,
  "total_invoice": 15000000,
  "invoice_outstanding": 2500000,
  "invoice_due_count": 1,
  "incomplete_payments_summary": []
}
```

## Invoice Types

### `GET /api/finance/invoice-types`

Mengambil semua master tipe invoice.

### `POST /api/finance/invoice-types`

Membuat tipe invoice.

Body:

```json
{
  "code_type": "IP",
  "description": "Invoice Progress"
}
```

Validasi:

| Field | Rule |
| --- | --- |
| `code_type` | required, string, max:255 |
| `description` | required, string, max:255 |

### `GET /api/finance/invoice-types/{id}`

Mengambil detail tipe invoice.

### `PUT /api/finance/invoice-types/{id}`

Memperbarui tipe invoice.

Body:

```json
{
  "code_type": "DP",
  "description": "Down Payment"
}
```

### `DELETE /api/finance/invoice-types/{id}`

Menghapus tipe invoice.

Response:

```json
{
  "message": "InvoiceType deleted successfully"
}
```

## Invoices

### `GET /api/finance/invoices`

Mengambil daftar invoice untuk project tertentu.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `project_id` | ya | PN number project |

Response memuat `invoices`, `total_payment`, dan `outstanding_payment`.

Error jika `project_id` kosong:

```json
{
  "error": "project_id is required"
}
```

### `GET /api/finance/invoice-list`

Mengambil semua invoice dengan filter periode.

Query opsional:

| Field | Default | Keterangan |
| --- | --- | --- |
| `year` | tahun terakhir dari data invoice | Tahun invoice |
| `range_type` | `yearly` | `yearly`, `monthly`, `weekly`, atau `custom` |
| `month` | null | Bulan untuk `range_type=monthly` |
| `from_date` | null | Tanggal awal untuk `range_type=custom` |
| `to_date` | null | Tanggal akhir untuk `range_type=custom` |

Response memuat metadata filter, `total_invoices`, `total_invoice_value`, dan `data`.

### `POST /api/finance/invoices`

Membuat invoice baru. Nomor invoice akan dibuat dari `invoice_type_id`, tahun berjalan, dan sequence global.

Body:

```json
{
  "project_id": "26001",
  "invoice_type_id": 1,
  "no_faktur": "010.001-26.00000001",
  "invoice_date": "2026-04-19",
  "invoice_description": "Progress 1",
  "invoice_value": 10000000,
  "invoice_due_date": "2026-05-19",
  "payment_status": "unpaid",
  "remarks": "Termin pertama",
  "currency": "IDR",
  "rate_usd": null,
  "is_ppn": true,
  "is_pph23": false,
  "is_pph42": false,
  "invoice_sequence": 1,
  "nilai_ppn": null,
  "nilai_pph23": null,
  "nilai_pph42": null
}
```

Validasi penting:

| Field | Rule |
| --- | --- |
| `project_id` | required, string |
| `invoice_type_id` | nullable, integer |
| `invoice_date` | nullable, date |
| `invoice_value` | nullable, numeric |
| `invoice_due_date` | nullable, date |
| `payment_status` | nullable, in:`unpaid`,`partial`,`paid` |
| `currency` | nullable, in:`IDR`,`USD` |
| `rate_usd` | nullable, numeric |
| `is_ppn`, `is_pph23`, `is_pph42` | nullable, boolean |
| `invoice_sequence` | nullable, integer, min:1 |

Business rule:

- Total `invoice_value` dalam satu project tidak boleh melebihi `project.po_value`.
- Sequence invoice tidak boleh duplikat untuk tahun berjalan.
- Jika `is_pph23` atau `is_pph42` aktif, sistem membuat data holding tax terkait.

### `GET /api/finance/invoices/next-id`

Melihat invoice ID berikutnya tanpa membuat invoice.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_type_id` | tidak | ID tipe invoice |
| `invoice_sequence` | tidak | Sequence manual |

Response:

```json
{
  "next_invoice_id": "IP/26/0001"
}
```

### `GET /api/finance/invoices/validate-sequence`

Memvalidasi apakah sequence invoice tersedia pada tahun berjalan.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_type_id` | tidak | ID tipe invoice |
| `invoice_sequence` | ya | Sequence yang dicek |

Response:

```json
{
  "available": true,
  "message": "Sequence is available"
}
```

### `GET /api/finance/invoice-summary`

Mengambil summary invoice per project.

Query opsional sama seperti `GET /api/finance/invoice-list`: `year`, `range_type`, `month`, `from_date`, `to_date`.

Response memuat `projects` dengan nilai seperti `project_value`, `invoice_total`, `payment_total`, `outstanding_invoice`, `outstanding_amount`, dan `invoice_progress`.

### `GET /api/finance/invoices/validate`

Memvalidasi nilai invoice terhadap nilai project tanpa menyimpan data.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `project_id` | ya | PN number project |
| `invoice_value` | ya | Nilai invoice yang akan dicek |
| `invoice_id` | tidak | Diisi saat validasi update agar invoice lama dikecualikan |

Response valid:

```json
{
  "valid": true,
  "message": "Invoice value is within project limits",
  "current_total": 0,
  "new_total": 10000000,
  "project_value": 50000000
}
```

### `GET /api/finance/invoices/preview-taxes`

Menghitung preview pajak invoice tanpa menyimpan data.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_value` | ya | Nilai DPP |
| `currency` | tidak | `IDR` atau `USD` |
| `rate_usd` | tidak | Kurs jika currency `USD` |
| `is_ppn` | tidak | `0`, `1`, `true`, atau `false` |
| `is_pph23` | tidak | `0`, `1`, `true`, atau `false` |
| `is_pph42` | tidak | `0`, `1`, `true`, atau `false` |

Response:

```json
{
  "ppn_rate": 0.11,
  "pph23_rate": 0.0265,
  "pph42_rate": 0,
  "nilai_ppn": 1100000,
  "nilai_pph23": 265000,
  "nilai_pph42": 0,
  "total_invoice": 11100000,
  "expected_payment": 10835000
}
```

### `GET /api/finance/invoices/{id}`

Mengambil detail invoice beserta project, tipe invoice, payments, dan `total_payment_amount`.

### `PUT /api/finance/invoices/{id}`

Memperbarui invoice.

Body:

```json
{
  "invoice_id": "IP/26/0007",
  "invoice_number_in_project": 7,
  "project_id": "26001",
  "invoice_type_id": 1,
  "no_faktur": "010.001-26.00000007",
  "invoice_date": "2026-04-19",
  "invoice_description": "Revisi invoice",
  "invoice_value": 10000000,
  "invoice_due_date": "2026-05-19",
  "payment_status": "unpaid",
  "remarks": "Nomor invoice direvisi",
  "currency": "IDR",
  "rate_usd": null,
  "is_ppn": true,
  "is_pph23": false,
  "is_pph42": false,
  "nilai_ppn": null,
  "nilai_pph23": null,
  "nilai_pph42": null
}
```

Catatan:

- `invoice_id` dapat diubah dari body selama belum dipakai invoice lain.
- Jika `invoice_id` berubah, sistem ikut memperbarui referensi invoice pada payment, holding tax, retention, dan delivery order.
- `invoice_number_in_project` dapat diubah dari body.
- Jika `invoice_type_id` berubah tanpa mengirim `invoice_id`, sistem tetap membuat ulang `invoice_id` dengan sequence lama.
- `project_id` tidak dapat diganti jika invoice sudah memiliki payment.

### `DELETE /api/finance/invoices/{id}`

Menghapus invoice beserta payment dan holding tax terkait. Retention terkait tidak dihapus, tetapi `invoice_id` pada retention di-set `null`.

Response:

```json
{
  "message": "Invoice deleted successfully"
}
```

## Invoice Payments

### `GET /api/finance/invoice-payments`

Mengambil daftar pembayaran untuk invoice tertentu.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_id` | ya | ID invoice |

Response memuat `payments`, `total_paid`, `remaining_payment`, dan `expected_payment`.

### `POST /api/finance/invoice-payments`

Membuat pembayaran invoice.

Body:

```json
{
  "invoice_id": "IP/26/0001",
  "payment_date": "2026-04-19",
  "payment_amount": 5000000,
  "currency": "IDR",
  "nomor_bukti_pembayaran": "BP-001",
  "notes": "Pembayaran pertama"
}
```

Validasi:

| Field | Rule |
| --- | --- |
| `invoice_id` | required, string, exists:`invoices.invoice_id` |
| `payment_date` | required, date |
| `payment_amount` | required, numeric, min:0 |
| `currency` | nullable, in:`IDR`,`USD` |
| `nomor_bukti_pembayaran` | nullable, string |

Business rule: total pembayaran tidak boleh melebihi `invoice.invoice_value`.

### `GET /api/finance/invoice-payments/validate`

Memvalidasi pembayaran tanpa menyimpan data.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_id` | ya | ID invoice |
| `payment_amount` | ya | Nilai pembayaran |
| `payment_id` | tidak | Diisi saat validasi update |

Response memuat `valid`, `message`, `current_total`, `new_total`, dan `expected_value`.

### `GET /api/finance/invoice-payments/{id}`

Mengambil detail pembayaran invoice.

### `PUT /api/finance/invoice-payments/{id}`

Memperbarui pembayaran invoice.

Body opsional:

```json
{
  "payment_date": "2026-04-20",
  "payment_amount": 7500000,
  "currency": "IDR",
  "nomor_bukti_pembayaran": "BP-002",
  "notes": "Revisi nilai"
}
```

### `DELETE /api/finance/invoice-payments/{id}`

Menghapus pembayaran invoice.

Response:

```json
{
  "message": "Payment deleted successfully"
}
```

## Taxes

### `GET /api/finance/taxes`

Mengambil semua master pajak.

### `POST /api/finance/taxes`

Membuat master pajak.

Body:

```json
{
  "name": "PPN",
  "rate": 0.11
}
```

Validasi:

| Field | Rule |
| --- | --- |
| `name` | required, string, max:255 |
| `rate` | required, numeric, min:0, max:100 |

### `GET /api/finance/taxes/{id}`

Mengambil detail master pajak.

### `PUT /api/finance/taxes/{id}`

Memperbarui master pajak.

### `DELETE /api/finance/taxes/{id}`

Menghapus master pajak.

Response:

```json
{
  "message": "Tax deleted successfully"
}
```

## Holding Taxes

### `GET /api/finance/holding-taxes/invoice`

Mengambil holding tax berdasarkan invoice.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_id` | ya | ID invoice |

Response success memuat `holding_tax`, `invoice`, dan `client_name`.

Response jika tidak ditemukan:

```json
{
  "error": "Holding tax not found for this invoice"
}
```

### `PUT /api/finance/holding-taxes/invoice`

Memperbarui holding tax berdasarkan invoice.

Query:

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `invoice_id` | ya | ID invoice |

Body:

```json
{
  "pph23_rate": 0.0265,
  "nilai_pph23": 265000,
  "pph42_rate": null,
  "nilai_pph42": null,
  "no_bukti_potong": "BUPOT-001",
  "nilai_potongan": 265000,
  "tanggal_wht": "2026-04-19"
}
```

## Retentions

### `GET /api/finance/retentions`

Mengambil daftar retention.

Query opsional:

| Field | Keterangan |
| --- | --- |
| `project_id` | Filter berdasarkan PN number project |

### `GET /api/finance/retentions/{id}`

Mengambil detail retention.

### `PUT /api/finance/retentions/{id}`

Memperbarui retention.

Body:

```json
{
  "project_id": "26001",
  "retention_due_date": "2026-05-19",
  "retention_value": 1000000,
  "invoice_id": "IP/26/0001"
}
```

### `DELETE /api/finance/retentions/{id}`

Menghapus retention.

Response:

```json
{
  "message": "Retention deleted successfully"
}
```

## Delivery Orders

### `GET /api/finance/delivery-orders`

Mengambil daftar delivery order.

### `POST /api/finance/delivery-orders`

Membuat delivery order. Sistem membuat `do_number` dan `do_no` otomatis dengan format tahun berjalan.

Body:

```json
{
  "do_description": "Pengiriman dokumen invoice",
  "pn_id": 26001,
  "return_date": "2026-04-30",
  "invoice_id": null,
  "do_send": "2026-04-19"
}
```

Validasi:

| Field | Rule |
| --- | --- |
| `do_description` | nullable, string |
| `pn_id` | required, integer, exists:`projects.pn_number` |
| `return_date` | nullable, date |
| `invoice_id` | nullable, integer, exists:`invoices.id` |
| `do_send` | nullable, date |

### `GET /api/finance/delivery-orders/{id}`

Mengambil detail delivery order.

### `PUT /api/finance/delivery-orders/{id}`

Memperbarui delivery order.

### `DELETE /api/finance/delivery-orders/{id}`

Menghapus delivery order.

Response:

```json
{
  "message": "Delivery order deleted successfully"
}
```

## Request Invoices

Endpoint request invoice menggunakan controller finance, tetapi route-nya tidak berada di prefix `/finance`.

### `GET /api/request-invoices-summary`

Mengambil summary request invoice per project.

Query opsional: `year`, `range_type`, `month`, `from_date`, `to_date`.

### `GET /api/request-invoices-list`

Mengambil daftar request invoice dengan filter periode.

Query opsional: `year`, `range_type`, `month`, `from_date`, `to_date`.

### `GET /api/request-invoices-list/{id}`

Mengambil detail request invoice dari sisi finance approval/list.

### `POST /api/request-invoices-list/{id}/approve`

Approve request invoice.

Body:

```json
{
  "pin": "123456"
}
```

Validasi dan business rule:

- `pin` wajib.
- PIN harus cocok dengan user yang login.
- Request invoice harus berstatus `pending`.

### `GET /api/request-invoices/{pn_number}`

Mengambil daftar request invoice berdasarkan PN number project.

### `GET /api/request-invoices/{pn_number}/phc-documents`

Mengambil dokumen PHC yang applicable untuk project.

### `POST /api/request-invoices`

Membuat request invoice.

Body:

```json
{
  "project_id": "26001",
  "description": "Request invoice termin 1",
  "documents": [
    {
      "document_preparation_id": 1,
      "notes": "Dokumen lengkap"
    }
  ]
}
```

Validasi:

| Field | Rule |
| --- | --- |
| `project_id` | required, exists:`projects.pn_number` |
| `description` | nullable, string |
| `documents` | array |
| `documents.*.document_preparation_id` | required, exists:`document_preparations.id` |
| `documents.*.notes` | nullable, string |

Nomor request dibuat otomatis dengan format `{project_id}/{sequence 3 digit}`, misalnya `26001/001`.

### `GET /api/request-invoices/show/{id}`

Mengambil detail request invoice berdasarkan ID.

### `PUT /api/request-invoices/{id}`

Memperbarui request invoice.

Body:

```json
{
  "description": "Update request invoice",
  "documents": [
    {
      "document_preparation_id": 1,
      "notes": "Catatan baru"
    }
  ]
}
```

Jika `documents` dikirim, dokumen lama akan dihapus dan diganti dengan daftar baru.
