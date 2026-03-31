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
        | 1. Generate Mongo DB name
        |--------------------------------------------------------------------------
        */
        $companyName = $input['company_name'] ?? $input['name'] . "'s Company";

        $databaseName = 'tenant_' . Str::lower(Str::random(8));

        /*
        |--------------------------------------------------------------------------
        | 2. Create Tenant
        |--------------------------------------------------------------------------
        */
        $tenant = Tenant::create([
            'name' => Str::slug($companyName),
            'company_name' => $companyName,
            'slug' => Str::slug($companyName) . '-' . Str::random(5),
            'database_name' => $databaseName, // 🔥 IMPORTANT
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. Create User
        |--------------------------------------------------------------------------
        */
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => 'brand',
            'tenant_id' => $tenant->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4. (Optional but recommended) Initialize Mongo DB
        |--------------------------------------------------------------------------
        */
        config([
            'database.connections.mongodb.database' => $databaseName
        ]);

        // create first collection (Mongo creates DB automatically)
        \DB::connection('mongodb')
            ->table('shopify_orders')
            ->insert([
                'init' => true,
                'created_at' => now(),
            ]);

        return $user;
    }
}