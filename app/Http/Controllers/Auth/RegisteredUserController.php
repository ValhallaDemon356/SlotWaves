<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Airport;
use App\Enums\UserRole;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $airports = Airport::orderBy('iata_code')->get();
        return view('auth.register', compact('airports'));
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'home_airport_id' => ['nullable', 'exists:airports,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => UserRole::Viewer, // Default role for self-registration
            'home_airport_id' => $request->home_airport_id,
            'department' => $request->department,
            'phone' => $request->phone,
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Record audit trail correctly matching AuditLog::record signature
        \App\Models\AuditLog::record(
            'register',
            $user->id,
            $user,
            [],
            ['home_airport_id' => $user->home_airport_id],
            'New user registered self-service'
        );

        return redirect(route('dashboard', absolute: false));
    }
}
