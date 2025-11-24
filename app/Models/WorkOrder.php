<?php

namespace App\Models;

use App\Traits\ActivityLoggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory, ActivityLoggable;

    protected $fillable = [
        'project_id',
        'purpose_id',
        'wo_date',
        'wo_number_in_project',
        'wo_kode_no',
        'location',
        'vehicle_no',
        'driver',
        'total_mandays_eng',
        'total_mandays_elect',
        'add_work',
        'approved_by',
        'status',
        'start_work_time',
        'stop_work_time',
        'continue_date',
        'continue_time',
        'client_note',
        'scheduled_start_working_date',
        'scheduled_end_working_date',
        'actual_start_working_date',
        'actual_end_working_date',
        'accomodation',
        'material_required',
        'wo_count',
        'client_approved',
        'created_by',
        'accepted_by',
    ];

    protected $casts = [
        'total_mandays_eng'       => 'integer',
        'total_mandays_elect'     => 'integer',
        'wo_count'                => 'integer',
        'add_work'                => 'boolean',
        'client_approved'         => 'boolean',
        'wo_date'                 => 'datetime',
        'start_work_time'         => 'datetime',
        'stop_work_time'          => 'datetime',
        'continue_date'           => 'date',
        'continue_time'           => 'datetime:H:i', // cast ke jam saja
        'scheduled_start_working_date' => 'date',
        'scheduled_end_working_date'   => 'date',
        'actual_start_working_date'    => 'date',
        'actual_end_working_date'      => 'date',
    ];

    /** 
     * ENUM status yang mungkin dipakai
     */
    public const STATUS_WAITING_APPROVAL      = 'waiting approval';
    public const STATUS_APPROVED              = 'approved';
    public const STATUS_WAITING_CLIENT        = 'waiting client approval';
    public const STATUS_FINISHED              = 'finished';

    /**
     * Relasi ke Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'pn_number');
    }

    /**
     * Relasi ke Purpose
     */
    public function purpose()
    {
        return $this->belongsTo(PurposeWorkOrders::class, 'purpose_id');
    }

    /**
     * Relasi ke PIC (banyak)
     */
    public function pics()
    {
        return $this->hasMany(WorkOrderPic::class);
    }

    /**
     * Relasi ke Descriptions (banyak)
     */
    public function descriptions()
    {
        return $this->hasMany(WorkOrderDescription::class);
    }

    /**
     * Relasi ke Logs (berdasarkan work order, bukan project_id)
     * -> kalau memang log-nya per-wo, pakai foreignKey 'work_order_id'
     */
    public function logs()
    {
        return $this->hasMany(Log::class, 'work_order_id');
    }

    /**
     * Relasi ke User yang approve
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi ke User yang buat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke User yang menerima
     */
    public function acceptor()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * Scope untuk status tertentu
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Relasi ke Approvals (morphMany)
     */
    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /**
     * Accessor: gabungkan kode & nomor untuk display WO
     */
    public function getDisplayCodeAttribute(): string
    {
        return "{$this->wo_kode_no}-{$this->wo_number_in_project}";
    }
}
