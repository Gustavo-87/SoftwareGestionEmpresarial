<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'price', 'stock',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
