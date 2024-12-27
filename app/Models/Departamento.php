<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use HasFactory;
    protected $table = 'departamentos';
    protected $primaryKey = 'id';
    protected $fillable = ['code', 'name', 'is_active'];
    public function distritos()
    {
        return $this->hasMany(Distrito::class);
    }

}
