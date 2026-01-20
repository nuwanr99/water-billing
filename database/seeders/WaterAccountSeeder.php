<?php

namespace Database\Seeders;

use App\Models\BillingCategory;
use App\Models\User;
use App\Models\WaterAccount;
use Illuminate\Database\Seeder;

class WaterAccountSeeder extends Seeder
{
    /**
     * Seed demo members and their water accounts.
     */
    public function run(): void
    {
        if (BillingCategory::doesntExist()) {
            $this->call(BillingCategorySeeder::class);
        }

        $categories = BillingCategory::all();

        $member = User::where('email', 'member@example.com')->first()
            ?? User::factory()->create([
                'first_name' => 'Demo',
                'last_name' => 'Member',
                'email' => 'member@example.com',
            ]);

        $member->assignRole('Member');

        if ($member->waterAccounts()->doesntExist()) {
            WaterAccount::factory()->count(2)->recycle($categories)->for($member, 'owner')->create();
            WaterAccount::factory()->inactive()->recycle($categories)->for($member, 'owner')->create();
        }

        User::factory()
            ->count(12)
            ->create()
            ->each(function (User $user) use ($categories): void {
                $user->assignRole('Member');

                WaterAccount::factory()
                    ->count(random_int(1, 3))
                    ->recycle($categories)
                    ->for($user, 'owner')
                    ->create();
            });
    }
}
