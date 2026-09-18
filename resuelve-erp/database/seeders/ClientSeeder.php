<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'name' => 'Carlos Ramírez',
                'document' => '1001001001',
                'phone' => '3001112233',
                'email' => 'carlos.ramirez@example.com',
            ],
            [
                'name' => 'Laura Gómez',
                'document' => '1001001002',
                'phone' => '3002223344',
                'email' => 'laura.gomez@example.com',
            ],
            [
                'name' => 'Andrés Martínez',
                'document' => '1001001003',
                'phone' => '3003334455',
                'email' => 'andres.martinez@example.com',
            ],
            [
                'name' => 'Diana López',
                'document' => '1001001004',
                'phone' => '3004445566',
                'email' => 'diana.lopez@example.com',
            ],
            [
                'name' => 'Felipe Torres',
                'document' => '1001001005',
                'phone' => '3005556677',
                'email' => 'felipe.torres@example.com',
            ],
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(
                ['document' => $client['document']],
                $client
            );
        }
    }
}
