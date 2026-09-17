<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CampanieSiteTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'viorel.dobrisor@radacini-grup.ro')->first();

        if (!$user) {
            $this->command->error('User viorel.dobrisor@radacini-grup.ro not found, skipping.');
            return;
        }

        EmailTemplate::updateOrCreate(
            [
                'user_id' => $user->id,
                'name' => 'Campanie site',
            ],
            [
                'subject' => 'Publică gratuit anunțul mașinii tale pe Rădăcini',
                'content' => '@view:emails.campaigns.campanie-site',
                'is_html' => true,
            ]
        );
    }
}
