<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'document',
        'phone',
        'email',
    ];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
