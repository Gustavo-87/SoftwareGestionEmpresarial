<?php
namespace App\Http\Middleware;
use App\Models\AuditLog; use Closure; use Illuminate\Http\Request; use Illuminate\Support\Facades\Schema; use Symfony\Component\HttpFoundation\Response;
class AuditMutations { public function handle(Request $r,Closure $next):Response{$response=$next($r);if(!$r->attributes->get('auditoria_especifica_registrada')&&!$r->session()->has('errors')&&$r->user()&&in_array($r->method(),['POST','PUT','PATCH','DELETE'])&&$response->getStatusCode()<400&&Schema::hasTable('audit_logs'))AuditLog::create(['user_id'=>$r->user()->id,'action'=>$r->method().' '.$r->route()?->getName(),'auditable_type'=>$r->route('pqr')?'pqr':null,'auditable_id'=>$r->route('pqr')?->id,'ip_address'=>$r->ip(),'metadata'=>['path'=>$r->path()]]);return $response;} }
