<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PqrActivity extends Model
{
    public const PUBLIC_ACTIONS = ['created', 'updated', 'quick_action', 'sent_reply', 'tags_updated'];

    protected $fillable = ['pqr_id', 'user_id', 'action', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pqr()
    {
        return $this->belongsTo(Pqr::class);
    }
}
