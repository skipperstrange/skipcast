<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenresTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            ['genre' => 'Rock', 'slug' => 'rock'],
            ['genre' => 'Pop', 'slug' => 'pop'],
            ['genre' => 'Afro Pop', 'slug' => 'afro-pop'],
            ['genre' => 'House', 'slug' => 'house'],
            ['genre' => 'Contemporary', 'slug' => 'contemporary'],
            ['genre' => 'Hip Hop', 'slug' => 'hip-hop'],
            ['genre' => 'Jazz', 'slug' => 'jazz'],
            ['genre' => 'Classical', 'slug' => 'classical'],
            ['genre' => 'Electronic', 'slug' => 'electronic'],
            ['genre' => 'Reggae', 'slug' => 'reggae'],
            ['genre' => 'Country', 'slug' => 'country'],
            ['genre' => 'Blues', 'slug' => 'blues'],
            ['genre' => 'Metal', 'slug' => 'metal'],
        ];

        DB::table('genres')->insert($genres);
    }
} 