<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PqrAttachment extends Model
{
    protected $fillable = ['pqr_id', 'original_name', 'path', 'mime_type', 'size'];

    public function pqr()
    {
        return $this->belongsTo(Pqr::class);
    }
}
