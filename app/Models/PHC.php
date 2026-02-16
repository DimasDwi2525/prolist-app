<?php

namespace App\Models;

use App\Traits\ActivityLoggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PHC extends Model
{
    use HasFactory, ActivityLoggable;

    protected $table = "phcs";

    public const STATUS_WAITING_APPROVAL = 'pending';
    public const STATUS_APPROVED = 'ready';

    protected $fillable = [
        'project_id',
        'ho_marketings_id',
        'ho_engineering_id',
        'created_by',
        'notes',
        'start_date',
        'target_finish_date',
        'client_pic_name',
        'client_mobile',
        'client_reps_office_address',
        'client_site_address',
        'client_site_representatives',
        'site_phone_number',
        'status',
        'pic_engineering_id',
        'pic_marketing_id',
        'costing_by_marketing',
        'boq',
        'retention',
        'warranty',
        'penalty',
        'handover_date',
        'retention_percentage',
        'retention_months',
        'warranty_date'
    ];

    protected $appends = ['display_status'];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'pn_number');
    }


    public function hoMarketing()
    {
        return $this->belongsTo(User::class, 'ho_marketings_id');
    }

    public function hoEngineering()
    {
        return $this->belongsTo(User::class, 'ho_engineering_id');
    }

    public function picEngineering()
    {
        return $this->belongsTo(User::class, 'pic_engineering_id');
    }

    public function picMarketing()
    {
        return $this->belongsTo(User::class, 'pic_marketing_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentPreparations()
    {
        return $this->hasMany(DocumentPreparation::class, 'phc_id');
    }

    // public function approvals()
    // {
    //     return $this->hasMany(PhcApproval::class, 'phc_id'); // foreign key yang benar
    // }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    // Tambahkan ini untuk memastikan ho_engineering_id bisa diisi
    protected $attributes = [
        'ho_engineering_id' => null
    ];

    // Tambahkan event observer
    protected static function booted()
    {
        static::updating(function ($phc) {
            if ($phc->isDirty('ho_engineering_id')) {
                // Log::info("HO Engineering Updated", [
                //     'phc_id' => $phc->id,
                //     'old_value' => $phc->getOriginal('ho_engineering_id'),
                //     'new_value' => $phc->ho_engineering_id
                // ]);
            }
        });
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'approved',
            default => 'waiting approval',
        };
    }

}
