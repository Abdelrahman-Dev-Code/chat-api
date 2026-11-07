<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a few users and a chat history.
     */
    public function run(): void
    {
        User::factory()
            ->count(5)
            ->has(Message::factory()->count(10))
            ->create();
    }
}
