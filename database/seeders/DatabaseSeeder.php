<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed default categories
        $categories = [
            ['code' => 'road_damage',  'name' => 'Jalan Rusak'],
            ['code' => 'waste',        'name' => 'Sampah Menumpuk'],
            ['code' => 'drainage',     'name' => 'Drainase Tersumbat'],
            ['code' => 'streetlight',  'name' => 'Lampu Jalan Mati'],
            ['code' => 'other',        'name' => 'Lainnya'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['code' => $cat['code']], $cat + ['is_active' => true]);
        }

        // Demo admin account (not for production)
        User::firstOrCreate(
            ['email' => 'admin@solvara.local'],
            [
                'name' => 'Admin Solvara',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // Demo officer
        User::firstOrCreate(
            ['email' => 'officer@solvara.local'],
            [
                'name' => 'Petugas Solvara',
                'password' => Hash::make('password'),
                'role' => 'officer',
            ]
        );

        // Demo citizen
        User::firstOrCreate(
            ['email' => 'citizen@solvara.local'],
            [
                'name' => 'Warga Solvara',
                'password' => Hash::make('password'),
                'role' => 'citizen',
            ]
        );
    }
}
