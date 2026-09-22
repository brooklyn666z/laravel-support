<?php

declare(strict_types=1);

namespace Fillindev\Support\Database\Seeders;

use Fillindev\Support\SupportTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $table = SupportTables::name('ticket_categories');

        $categories = [
            ['code' => 'billing', 'name' => 'Биллинг', 'sort' => 10],
            ['code' => 'technical', 'name' => 'Технический вопрос', 'sort' => 20],
            ['code' => 'access', 'name' => 'Доступ', 'sort' => 30],
            ['code' => 'other', 'name' => 'Другое', 'sort' => 40],
        ];

        foreach ($categories as $category) {
            $exists = DB::table($table)->where('code', $category['code'])->exists();

            if ($exists) {
                DB::table($table)->where('code', $category['code'])->update([
                    'name' => $category['name'],
                    'sort' => $category['sort'],
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table($table)->insert([
                ...$category,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
