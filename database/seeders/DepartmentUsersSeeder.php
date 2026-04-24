<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DepartmentUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // Ventas
            [
                'name' => 'Carlos Vendedor',
                'email' => 'carlos.ventas@empresa.com',
                'department' => 'ventas',
                'is_active' => true,
            ],
            [
                'name' => 'María Comercial',
                'email' => 'maria.ventas@empresa.com',
                'department' => 'ventas',
                'is_active' => true,
            ],

            // Servicio Técnico
            [
                'name' => 'Juan Técnico',
                'email' => 'juan.tecnico@empresa.com',
                'department' => 'servicio_tecnico',
                'is_active' => true,
            ],
            [
                'name' => 'Ana Soporte',
                'email' => 'ana.soporte@empresa.com',
                'department' => 'servicio_tecnico',
                'is_active' => true,
            ],

            // Garantías
            [
                'name' => 'Luis Garantías',
                'email' => 'luis.garantias@empresa.com',
                'department' => 'garantias',
                'is_active' => true,
            ],

            // Refacciones
            [
                'name' => 'Patricia Refacciones',
                'email' => 'patricia.refacciones@empresa.com',
                'department' => 'refacciones',
                'is_active' => true,
            ],

            // Administración
            [
                'name' => 'Roberto Admin',
                'email' => 'roberto.admin@empresa.com',
                'department' => 'administracion',
                'is_active' => true,
            ],
            [
                'name' => 'Elena Supervisor',
                'email' => 'elena.admin@empresa.com',
                'department' => 'administracion',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password123'), // Contraseña temporal
                'department' => $userData['department'],
                'is_active' => $userData['is_active'],
            ]);
        }

        $this->command->info('✅ Asesores por departamento creados exitosamente');
    }
}