<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Domain;
use App\Models\Admin;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\Link;
use App\Models\User;
use App\Models\CardType;
use App\Models\Card;
use App\Models\Order;
use App\Models\Discount;
use App\Models\CardCredit;
use App\Models\AlertApp;
use App\Models\AlertMessage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('admin123');

        // ── Truncate all tables except admins & admin_invitations ──
        Schema::disableForeignKeyConstraints();

        $tables = [
            'alert_messages', 'alert_apps', 'card_credits', 'discounts',
            'orders', 'cards', 'card_types', 'links', 'managers', 'users',
            'entities', 'domains', 'invitations', 'terms',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        // ── Everything inside a transaction ──
        DB::transaction(function () use ($password) {

            // ╔══════════════════════════════════════╗
            // ║  0. ADMIN PLATEFORME                 ║
            // ╚══════════════════════════════════════╝
            Admin::updateOrCreate(
                ['email' => 'dev@ollaid.com'],
                [
                    'name'      => 'Dev OLLAID',
                    'password'  => $password,
                    'status'    => 'active',
                    'reference' => 'AD-DEV',
                    'ccphone'   => '+221',
                    'phone'     => '77 000 00 00',
                ]
            );

            // ╔══════════════════════════════════════╗
            // ║  1. DOMAINS                          ║
            // ╚══════════════════════════════════════╝
            $this->call(DomainSeeder::class);

            $domainPharmacie = Domain::where('name', 'Pharmacie')->first();

            // ╔══════════════════════════════════════╗
            // ║  2. ENTITY (Pharmacie Unique)         ║
            // ╚══════════════════════════════════════╝
            $pharmacie = Entity::create([
                'domain_id'       => $domainPharmacie->id,
                'name'            => 'Pharmacie Mame Diarra',
                'logo'            => 'logos/pharmacie-mame-diarra.png',
                'primary_color'   => '#0D9488',
                'secondary_color' => '#F0FDFA',
                'address'         => '12 Rue Carnot',
                'town'            => 'Dakar',
                'country'         => 'Sénégal',
                'email'           => 'pharmacie.mamediarra@kolere.com',
                'ccphone'         => '+221',
                'phone'           => '77 123 45 67',
            ]);

            // ╔══════════════════════════════════════╗
            // ║  3. MANAGER + LINK                   ║
            // ╚══════════════════════════════════════╝
            $manager = Manager::create([
                'name'      => 'Moussa Diop',
                'email'     => 'shop@df.com',
                'ccphone'   => '+221',
                'phone'     => '77 100 00 01',
                'status'    => 'active',
                'password'  => $password,
                'reference' => 'MA-001',
            ]);

            Link::create(['manager_id' => $manager->id, 'entity_id' => $pharmacie->id, 'is_admin' => true]);

            // ╔══════════════════════════════════════╗
            // ║  4. USERS (Clients)                   ║
            // ╚══════════════════════════════════════╝
            $user1 = User::create([
                'name'     => 'Ibrahima Ndiaye',
                'email'    => 'client1@df.com',
                'password' => $password,
            ]);

            $user2 = User::create([
                'name'     => 'Fatou Ba',
                'email'    => 'client2@df.com',
                'password' => $password,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  5. CARD TYPES                        ║
            // ╚══════════════════════════════════════╝
            $bronze = CardType::create(['name' => 'Bronze', 'discount' => 5, 'status' => 'active']);
            $silver = CardType::create(['name' => 'Silver', 'discount' => 10, 'status' => 'active']);
            $gold   = CardType::create(['name' => 'Gold', 'discount' => 15, 'status' => 'active']);

            // ╔══════════════════════════════════════╗
            // ║  6. CARDS (2 users × 1 pharmacy)       ║
            // ╚══════════════════════════════════════╝
            $card1 = Card::create([
                'status'       => 'active',
                'entity_id'    => $pharmacie->id,
                'user_id'      => $user1->id,
                'card_type_id' => $bronze->id,
                'credit'       => 1500,
            ]);

            $card2 = Card::create([
                'status'       => 'active',
                'entity_id'    => $pharmacie->id,
                'user_id'      => $user2->id,
                'card_type_id' => $silver->id,
                'credit'       => 800,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  7. DISCOUNTS                        ║
            // ╚══════════════════════════════════════╝
            $discount1 = Discount::create([
                'entity_id'       => $pharmacie->id,
                'card_id'         => $card1->id,
                'discount_type'   => 'percentage',
                'discount_value'  => 10,
                'discount_amount' => null,
                'expiration'      => '2026-06-30',
            ]);

            $discount2 = Discount::create([
                'entity_id'       => $pharmacie->id,
                'card_id'         => $card2->id,
                'discount_type'   => 'fixed',
                'discount_value'  => null,
                'discount_amount' => 2000,
                'expiration'      => '2026-06-30',
            ]);

            // ╔══════════════════════════════════════╗
            // ║  8. ORDERS (commandes clients)         ║
            // ╚══════════════════════════════════════╝
            $order1 = Order::create([
                'name'        => 'Doliprane 1000mg',
                'description' => 'Boîte de 8 comprimés – antidouleur',
                'price'       => 2500,
                'status'      => 'completed',
                'amount'      => 2500,
                'discount'    => 250,
                'total'       => 2250,
                'discount_id' => $discount1->id,
            ]);

            $order2 = Order::create([
                'name'        => 'Vitamine C Upsa 1g',
                'description' => 'Tube de 20 comprimés effervescents',
                'price'       => 3800,
                'status'      => 'completed',
                'amount'      => 3800,
                'discount'    => 380,
                'total'       => 3420,
                'discount_id' => $discount1->id,
            ]);

            $order3 = Order::create([
                'name'        => 'Sirop contre la toux Toplexil',
                'description' => 'Flacon 150ml – toux sèche',
                'price'       => 4500,
                'status'      => 'pending',
                'amount'      => 4500,
                'discount'    => 0,
                'total'       => 4500,
                'discount_id' => $discount2->id,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  9. CARD CREDITS                     ║
            // ╚══════════════════════════════════════╝
            CardCredit::create(['card_id' => $card1->id, 'order_id' => $order1->id, 'amount' => 2250, 'credit' => 225]);
            CardCredit::create(['card_id' => $card1->id, 'order_id' => $order2->id, 'amount' => 3420, 'credit' => 342]);
            CardCredit::create(['card_id' => $card2->id, 'order_id' => $order3->id, 'amount' => 4500, 'credit' => 450]);

            // ╔══════════════════════════════════════╗
            // ║  10. ALERT APPS                      ║
            // ╚══════════════════════════════════════╝
            AlertApp::create([
                'entity_id'   => $pharmacie->id,
                'title'       => 'Nouveau client fidélité',
                'description' => 'Ibrahima Ndiaye vient de s\'inscrire au programme de fidélité.',
                'read'        => false,
                'card_id'     => $card1->id,
                'manager_id'  => $manager->id,
            ]);

            AlertApp::create([
                'entity_id'   => $pharmacie->id,
                'title'       => 'Seuil de points atteint',
                'description' => 'La carte de Fatou Ba a atteint 800 points. Proposez-lui un passage Silver.',
                'read'        => true,
                'card_id'     => $card2->id,
                'manager_id'  => $manager->id,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  11. ALERT MESSAGES                   ║
            // ╚══════════════════════════════════════╝
            AlertMessage::create([
                'title'       => 'Bienvenue sur Kolere !',
                'image'       => null,
                'description' => 'Votre pharmacie est maintenant active. Commencez à fidéliser vos clients dès maintenant.',
                'entity_id'   => $pharmacie->id,
                'read'        => true,
            ]);
        });
    }
}
