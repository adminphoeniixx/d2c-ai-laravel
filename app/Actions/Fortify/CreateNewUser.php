<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ])->validate();

        /*
        |--------------------------------------------------------------------------
        | 1. Create Tenant Automatically
        |--------------------------------------------------------------------------
        */
        $companyName = $input['company_name'] ?? $input['name'] . "'s Company";

        $tenant = Tenant::create([
            'name' => Str::slug($companyName),
            'company_name' => $companyName,
            'slug' => Str::slug($companyName) . '-' . Str::random(5),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Create Brand Admin User
        |--------------------------------------------------------------------------
        */
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => 'brand',
            'tenant_id' => $tenant->id,
        ]);
    }
}