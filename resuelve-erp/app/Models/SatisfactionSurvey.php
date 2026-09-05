<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class SatisfactionSurvey extends Model { protected $fillable=['pqr_id','user_id','rating','comment']; public function pqr(){return $this->belongsTo(Pqr::class);} }
