<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Nutrisi',     'description' => 'Materi tentang gizi, asupan makanan, dan nutrisi untuk kesehatan pencernaan.'],
            ['name' => 'Anatomi',     'description' => 'Pengenalan organ-organ pencernaan dan cara kerjanya secara ilmiah.'],
            ['name' => 'Pencegahan',  'description' => 'Langkah-langkah pencegahan penyakit dan kebiasaan hidup sehat.'],
            ['name' => 'Penyakit',    'description' => 'Informasi mendalam tentang berbagai penyakit saluran pencernaan.'],
            ['name' => 'Gaya Hidup',  'description' => 'Tips dan kebiasaan gaya hidup sehat yang mendukung sistem pencernaan.'],
            ['name' => 'Umum',        'description' => 'Materi edukasi umum seputar kesehatan pencernaan.'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                ['name' => $cat['name'], 'description' => $cat['description']],
            );
        }
    }
}
