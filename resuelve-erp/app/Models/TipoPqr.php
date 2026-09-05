<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPqr extends Model
{
    use HasFactory;
    protected $fillable = ['nombre', 'descripcion'];

    public function pqrs()
    {
        return $this->hasMany(Pqr::class);
    }
}