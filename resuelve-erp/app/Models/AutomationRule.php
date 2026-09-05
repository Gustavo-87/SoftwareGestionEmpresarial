<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class AutomationRule extends Model { protected $fillable=['name','tipo_pqr_id','assign_to_id','set_status','active']; protected function casts():array{return ['active'=>'boolean'];} public function type(){return $this->belongsTo(TipoPqr::class,'tipo_pqr_id');} public function assignee(){return $this->belongsTo(User::class,'assign_to_id');} }
