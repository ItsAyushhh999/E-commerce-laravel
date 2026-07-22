<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    const TARGET_TOTAL = 50_000;

    const BATCH_SIZE = 2_000;

    public function run(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        DB::connection()->disableQueryLog();

        // Additive: no truncate. Existing users are referenced by orders'
        // user_id, so truncating would orphan every order already seeded.
        $existing = DB::table('users')->count();
        $needed = self::TARGET_TOTAL - $existing;

        if ($needed <= 0) {
            $this->command->info("Already at or above target ({$existing}). Nothing to do.");

            return;
        }

        $this->command->info("Have {$existing}, adding ".number_format($needed).' more to reach '.number_format(self::TARGET_TOTAL).'...');

        $now = now()->format('Y-m-d H:i:s');
        $hashedPassword = Hash::make('password'); // hash once, reuse — hashing per-row is needless overhead here
        $totalBatches = (int) ceil($needed / self::BATCH_SIZE);
        $added = 0;

        // Counter starts after existing count so generated emails never
        // collide with ones already in the table — avoids Faker's unique()
        // modifier entirely, which gets slower as the table fills up (same
        // lesson as the SKU/variant seeding earlier).
        $counter = $existing;

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $count = min(self::BATCH_SIZE, $needed - $added);
            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $counter++;
                $rows[] = [
                    'name' => $this->randomName(),
                    'email' => 'user'.$counter.'@example.com',
                    'email_verified_at' => $now,
                    'password' => $hashedPassword,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($rows);
            $added += $count;

            if ($batch % 5 === 0) {
                $this->command->info('Users added: '.number_format($added).' / '.number_format($needed));
            }
        }

        $this->command->info('Users done! Total: '.number_format(DB::table('users')->count()));
    }

    private array $firstNames = ['James', 'Mary', 'Robert', 'Patricia', 'John', 'Jennifer', 'Michael', 'Linda', 'David', 'Sushmita', 'Prakash', 'Anita', 'Rajesh', 'Sunita'];

    private array $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Sharma', 'Shrestha', 'Thapa', 'Gurung', 'Rai', 'Poudel'];

    private function randomName(): string
    {
        return $this->firstNames[array_rand($this->firstNames)].' '.$this->lastNames[array_rand($this->lastNames)];
    }
}
