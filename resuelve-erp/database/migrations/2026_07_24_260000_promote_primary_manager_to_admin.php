<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void { DB::table('users')->where('email','gestionpqrs7@gmail.com')->where('role','gestor')->update(['role'=>'admin']); }
    public function down(): void { DB::table('users')->where('email','gestionpqrs7@gmail.com')->where('role','admin')->update(['role'=>'gestor']); }
};
