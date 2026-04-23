# Dokumentasi API PHC

Base URL: `/api`

Autentikasi: endpoint di dokumen ini berada di dalam middleware `auth:api`. Client harus mengirim token/session API sesuai mekanisme autentikasi aplikasi.

Format response umum: JSON. Response validasi Laravel menggunakan status `422`. Data PHC yang tidak ditemukan mengembalikan status `404`.

## Create PHC

### `POST /api/phc`

Membuat data PHC baru, membuat approval PHC, mengirim event notifikasi, dan mengisi tanggal PHC di project terkait.

Gunakan `multipart/form-data` jika mengirim file BOQ.

### Body

| Field | Wajib | Tipe | Keterangan |
| --- | --- | --- | --- |
| `project_id` | ya | string/integer | PN number project. Harus ada di `projects.pn_number`. |
| `handover_date` | tidak | date | Tanggal handover. |
| `start_date` | tidak | date | Tanggal mulai. |
| `target_finish_date` | tidak | date | Target selesai. |
| `client_pic_name` | tidak | string | Nama PIC client. |
| `client_mobile` | tidak | string | Nomor handphone client. |
| `client_reps_office_address` | tidak | string | Alamat kantor perwakilan client. |
| `client_site_representatives` | tidak | string | Perwakilan client di site. |
| `client_site_address` | tidak | string | Alamat site client. |
| `site_phone_number` | tidak | string | Nomor telepon site. |
| `pic_marketing_id` | ya | integer | User PIC marketing. Harus ada di `users.id`. |
| `pic_engineering_id` | tidak | integer | User PIC engineering. Harus ada di `users.id`. |
| `ho_marketings_id` | tidak | integer | User HO marketing. Harus ada di `users.id`. |
| `ho_engineering_id` | tidak | integer | User HO engineering. Harus ada di `users.id`. |
| `notes` | tidak | string | Catatan PHC. |
| `costing_by_marketing` | tidak | string | Kirim `A` untuk true/applicable. Selain `A` akan disimpan false. |
| `boq` | tidak | string | Kirim `A` untuk true/applicable. Selain `A` akan disimpan false. |
| `boq_file_path` | tidak | file | File BOQ. Maksimal 10 MB. Path tersimpan di `boq_file_path`. |
| `retention` | tidak | boolean | Status retention applicable. |
| `warranty` | tidak | boolean | Status warranty applicable. |
| `retention_percentage` | tidak | numeric | Persentase retention, `0` sampai `100`. |
| `retention_months` | tidak | integer | Durasi retention dalam bulan, minimal `0`. |
| `warranty_date` | tidak | date | Tanggal warranty. |
| `penalty` | tidak | string | Informasi penalty. |

### Contoh Request JSON tanpa file

```http
POST /api/phc
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

```json
{
  "project_id": "26001",
  "handover_date": "2026-04-21",
  "start_date": "2026-04-22",
  "target_finish_date": "2026-05-22",
  "client_pic_name": "Budi",
  "client_mobile": "08123456789",
  "client_reps_office_address": "Jakarta",
  "client_site_representatives": "Andi",
  "client_site_address": "Cikarang",
  "site_phone_number": "021123456",
  "pic_marketing_id": 12,
  "pic_engineering_id": 15,
  "ho_marketings_id": 3,
  "ho_engineering_id": 4,
  "notes": "PHC awal project",
  "costing_by_marketing": "A",
  "boq": "A",
  "retention": true,
  "warranty": true,
  "retention_percentage": 5,
  "retention_months": 12,
  "warranty_date": "2027-05-22",
  "penalty": "Sesuai kontrak"
}
```

### Contoh Request dengan File BOQ

```bash
curl -X POST "{base_url}/api/phc" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -F "project_id=26001" \
  -F "handover_date=2026-04-21" \
  -F "start_date=2026-04-22" \
  -F "target_finish_date=2026-05-22" \
  -F "client_pic_name=Budi" \
  -F "pic_marketing_id=12" \
  -F "pic_engineering_id=15" \
  -F "ho_marketings_id=3" \
  -F "ho_engineering_id=4" \
  -F "costing_by_marketing=A" \
  -F "boq=A" \
  -F "retention=true" \
  -F "warranty=true" \
  -F "retention_percentage=5" \
  -F "retention_months=12" \
  -F "warranty_date=2027-05-22" \
  -F "boq_file_path=@/path/to/boq.pdf"
```

### Response Berhasil

```json
{
  "status": "success",
  "message": "PHC created successfully, approvals assigned.",
  "data": {
    "phc": {
      "id": 1,
      "project_id": "26001",
      "status": "pending",
      "boq": 1,
      "boq_file_path": "phc_boq_files/example.pdf"
    },
    "approvers": [12, 4]
  }
}
```

### Business Rule Create

- `created_by` diambil dari user yang sedang login.
- `status` awal PHC adalah `pending`.
- Jika `boq_file_path` dikirim, file disimpan ke disk `public` di folder `phc_boq_files`.
- Jika `retention` bernilai true, sistem membuat data retention untuk project terkait.
- Approval dibuat untuk PIC marketing dan HO engineering jika tersedia.
- Jika HO engineering tidak dikirim, approval engineering dikirim ke user dengan role `project manager` dan `project controller`.

## Edit PHC

### `PUT /api/phc/{id}`

Memperbarui data PHC berdasarkan ID. Jika file BOQ baru dikirim, file lama akan dihapus dari storage dan diganti dengan file baru.

Gunakan `multipart/form-data` jika mengirim file BOQ. Untuk upload file, endpoint alternatif yang direkomendasikan adalah `POST /api/phc/{id}` karena beberapa server PHP tidak membaca file multipart pada request `PUT`.

### Path Parameter

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `id` | ya | ID PHC. |

### Body

| Field | Wajib | Tipe | Keterangan |
| --- | --- | --- | --- |
| `handover_date` | ya | date | Tanggal handover. |
| `start_date` | ya | date | Tanggal mulai. |
| `target_finish_date` | ya | date | Target selesai. |
| `client_pic_name` | tidak | string | Nama PIC client. |
| `client_mobile` | tidak | string | Nomor handphone client. |
| `client_reps_office_address` | tidak | string | Alamat kantor perwakilan client. |
| `client_site_representatives` | tidak | string | Perwakilan client di site. |
| `client_site_address` | tidak | string | Alamat site client. |
| `site_phone_number` | tidak | string | Nomor telepon site. |
| `pic_marketing_id` | tidak | integer | User PIC marketing. Harus ada di `users.id`. |
| `pic_engineering_id` | tidak | integer | User PIC engineering. Harus ada di `users.id`. |
| `ho_marketings_id` | tidak | integer | User HO marketing. Harus ada di `users.id`. |
| `ho_engineering_id` | tidak | integer | User HO engineering. Harus ada di `users.id`. |
| `notes` | tidak | string | Catatan PHC. |
| `costing_by_marketing` | tidak | string | Kirim `A` untuk true/applicable. Selain `A` akan disimpan false. |
| `boq` | tidak | string | Kirim `A` untuk true/applicable. Selain `A` akan disimpan false. |
| `boq_file_path` | tidak | file | File BOQ baru. Maksimal 10 MB. Jika dikirim, mengganti file lama. |
| `retention` | tidak | boolean | Status retention applicable. |
| `warranty` | tidak | boolean | Status warranty applicable. |
| `retention_percentage` | tidak | numeric | Persentase retention, `0` sampai `100`. |
| `retention_months` | tidak | integer | Durasi retention dalam bulan, minimal `0`. |
| `warranty_date` | tidak | date | Tanggal warranty. |
| `penalty` | tidak | string | Informasi penalty. |

Catatan implementasi: request update saat ini sebaiknya tetap mengirim field `retention` karena controller memakai nilai ini untuk sinkronisasi data retention.

### Contoh Request JSON tanpa file

```http
PUT /api/phc/1
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

```json
{
  "handover_date": "2026-04-21",
  "start_date": "2026-04-22",
  "target_finish_date": "2026-05-30",
  "client_pic_name": "Budi",
  "client_mobile": "08123456789",
  "client_reps_office_address": "Jakarta",
  "client_site_representatives": "Andi",
  "client_site_address": "Cikarang",
  "site_phone_number": "021123456",
  "pic_marketing_id": 12,
  "pic_engineering_id": 15,
  "ho_marketings_id": 3,
  "ho_engineering_id": 4,
  "notes": "Update target selesai",
  "costing_by_marketing": "A",
  "boq": "A",
  "retention": true,
  "warranty": true,
  "retention_percentage": 5,
  "retention_months": 12,
  "warranty_date": "2027-05-30",
  "penalty": "Sesuai kontrak"
}
```

### Endpoint Alternatif Untuk Upload File

### `POST /api/phc/{id}`

Endpoint ini menjalankan method controller yang sama dengan `PUT /api/phc/{id}`, tetapi lebih aman untuk request `multipart/form-data` yang berisi file.

### Contoh Request dengan File BOQ Baru

```bash
curl -X POST "{base_url}/api/phc/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -F "handover_date=2026-04-21" \
  -F "start_date=2026-04-22" \
  -F "target_finish_date=2026-05-30" \
  -F "client_pic_name=Budi" \
  -F "pic_marketing_id=12" \
  -F "pic_engineering_id=15" \
  -F "ho_marketings_id=3" \
  -F "ho_engineering_id=4" \
  -F "costing_by_marketing=A" \
  -F "boq=A" \
  -F "retention=true" \
  -F "warranty=true" \
  -F "retention_percentage=5" \
  -F "retention_months=12" \
  -F "warranty_date=2027-05-30" \
  -F "boq_file_path=@/path/to/boq-revisi.pdf"
```

Alternatif lain adalah method spoofing Laravel:

```bash
curl -X POST "{base_url}/api/phc/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -F "_method=PUT" \
  -F "handover_date=2026-04-21" \
  -F "start_date=2026-04-22" \
  -F "target_finish_date=2026-05-30" \
  -F "retention=true" \
  -F "warranty=true" \
  -F "boq_file_path=@/path/to/boq-revisi.pdf"
```

### Response Berhasil

```json
{
  "success": true,
  "message": "PHC berhasil diperbarui",
  "data": {
    "id": 1,
    "project_id": "26001",
    "boq": 1,
    "boq_file_path": "phc_boq_files/boq-revisi.pdf"
  }
}
```

### Error PHC Tidak Ditemukan

```json
{
  "success": false,
  "message": "PHC tidak ditemukan"
}
```

## Field File BOQ

| Field Request | Field Database | Storage |
| --- | --- | --- |
| `boq_file_path` | `boq_file_path` | disk `public`, folder `phc_boq_files` |

Validasi file:

| Field | Rule |
| --- | --- |
| `boq_file_path` | nullable, file, max:10240 |

`max:10240` berarti ukuran maksimal 10 MB.

## Status dan Nilai Boolean

Field `costing_by_marketing` dan `boq` memakai mapping radio:

| Nilai Request | Nilai Database |
| --- | --- |
| `A` | `1` |
| selain `A` atau kosong | `0` |

Field `retention` dan `warranty` memakai boolean Laravel. Nilai yang umum diterima: `true`, `false`, `1`, `0`, `"1"`, `"0"`.
