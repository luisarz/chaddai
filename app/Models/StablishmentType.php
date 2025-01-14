<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StablishmentType extends Model
{
    use HasFactory;
    use SoftDeletes;

    //
    protected $fillable = [
        'code',
        'name',
        'is_active'
    ];
}
