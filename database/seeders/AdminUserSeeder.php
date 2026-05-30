<?php
namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@erpsaas.com.br')],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make(env('ADMIN_PASSWORD', 'MudarEstaSenha123')),
                'role'      => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
