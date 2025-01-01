<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'transfer_number',
        'wherehouse_from',
        'user_send',
        'wherehouse_to',
        'user_recive',
        'transfer_date',
        'received_date',
        'total',
        'status_send',
        'status_received',
    ];

    public function wherehouse_from()
    {
        return $this->belongsTo(Branch::class);
    }
    public function user_send()
    {
        return $this->belongsTo(Employee::class);
    }
    public function wherehouse_to()
    {
        return $this->belongsTo(Branch::class);
    }
    public function user_recive()
    {
        return $this->belongsTo(Employee::class);
    }


}
