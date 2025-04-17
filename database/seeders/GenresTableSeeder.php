<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenresTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            'Rock',
            'Pop',
            'Hip Hop',
            'Jazz',
            'Classical',
            'Electronic',
            'Reggae',
            'Country',
            'Blues',
            'R&B',
            'Metal',
            'Folk',
            'Punk',
            'Indie',
            'Latin',
        ];

        $genreData = array_map(function ($genre) {
            return [
                'genre' => $genre,
                'slug' => Str::slug(strtolower($genre))
            ];
        }, $genres);

        DB::table('genres')->insert($genreData);
    }
} 