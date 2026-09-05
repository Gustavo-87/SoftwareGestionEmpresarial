<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PqrInternalComment extends Model {
    protected $fillable = ['pqr_id','user_id','body'];
    public function user() { return $this->belongsTo(User::class); }
    public function pqr() { return $this->belongsTo(Pqr::class); }
    public function communicationOperations() { return $this->hasMany(PqrCommunicationOperation::class); }
}
