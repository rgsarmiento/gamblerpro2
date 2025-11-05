<?php

namespace Database\Seeders;

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\{Casino, Sucursal, User};
use Illuminate\Support\Facades\Hash;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['master_admin', 'casino_admin', 'sucursal_admin', 'cajero'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
        $casino = Casino::firstOrCreate(['nombre' => 'Casino Demo']);
        $sucursal = Sucursal::firstOrCreate(['casino_id' => $casino->id, 'nombre' => 'Sucursal Centro']);


        $admin = User::firstOrCreate(['email' => 'admin@gamblerpro2.com'], [
            'name' => 'Admin Master',
            'password' => Hash::make('123456789'),
        ]);
        $admin->assignRole('master_admin');
    }
}
