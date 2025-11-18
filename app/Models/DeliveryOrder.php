<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'do_number';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'do_number',
        'do_no',
        'do_description',
        'pn_id',
        'return_date',
        'invoice_id',
        'do_send',
    ];

    protected $casts = [
        'return_date' => 'date:Y-m-d',
        'do_send' => 'date:Y-m-d',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'pn_id', 'pn_number');
    }
}
