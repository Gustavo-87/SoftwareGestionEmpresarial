<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
class ProfileController extends Controller {
    public function edit(Request $request): View { return view('profile.edit',['user'=>$request->user()]); }
    public function update(Request $request): RedirectResponse { $user=$request->user(); $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:150',Rule::unique('users')->ignore($user->id)],'current_password'=>['nullable','required_with:password','current_password'],'password'=>['nullable','confirmed',Password::min(8)]]); if(!empty($data['password']))$data['password']=Hash::make($data['password']); unset($data['current_password']); $user->update($data); return back()->with('success','Perfil actualizado.'); }
}
