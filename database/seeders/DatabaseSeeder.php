<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Domain;
use App\Models\Pricing;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\Link;
use App\Models\User;
use App\Models\CardType;
use App\Models\Card;
use App\Models\AppOrder;
use App\Models\AppSuscription;
use App\Models\AppPayment;
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
            'orders', 'app_payments', 'app_suscriptions', 'app_orders',
            'cards', 'card_types', 'links', 'managers', 'users',
            'entities', 'pricings', 'domains', 'invitations', 'terms',
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
            // ║  1. DOMAINS                          ║
            // ╚══════════════════════════════════════╝
            $this->call(DomainSeeder::class);

            $domainPharmacie = Domain::where('name', 'Pharmacie')->first();
            $domainCouture   = Domain::where('name', 'Couture')->first();

            // ╔══════════════════════════════════════╗
            // ║  2. PRICINGS                         ║
            // ╚══════════════════════════════════════╝
            $starter = Pricing::create([
                'name'        => 'Starter',
                'description' => 'Idéal pour démarrer. Accès basique à la plateforme.',
                'amount'      => 9900,
                'duration'    => 30,
            ]);

            $pro = Pricing::create([
                'name'        => 'Pro',
                'description' => 'Pour les boutiques en croissance. Fonctionnalités avancées.',
                'amount'      => 24900,
                'duration'    => 90,
            ]);

            $enterprise = Pricing::create([
                'name'        => 'Enterprise',
                'description' => 'Solution complète pour les grandes enseignes.',
                'amount'      => 79900,
                'duration'    => 365,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  3. ENTITIES (Boutiques)              ║
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

            $couture = Entity::create([
                'domain_id'       => $domainCouture->id,
                'name'            => 'Boutique Ndèye Fatou Couture',
                'logo'            => 'logos/ndeye-fatou-couture.png',
                'primary_color'   => '#7C3AED',
                'secondary_color' => '#F5F3FF',
                'address'         => '45 Avenue Blaise Diagne',
                'town'            => 'Dakar',
                'country'         => 'Sénégal',
                'email'           => 'ndeye.couture@kolere.com',
                'ccphone'         => '+221',
                'phone'           => '78 234 56 78',
            ]);

            // ╔══════════════════════════════════════╗
            // ║  4. MANAGERS + LINKS                  ║
            // ╚══════════════════════════════════════╝
            $manager1 = Manager::create([
                'name'      => 'Moussa Diop',
                'email'     => 'shop@df.com',
                'ccphone'   => '+221',
                'phone'     => '77 100 00 01',
                'status'    => 'active',
                'password'  => $password,
                'reference' => 'MA-001',
            ]);

            $manager2 = Manager::create([
                'name'      => 'Aminata Sall',
                'email'     => 'shop2@df.com',
                'ccphone'   => '+221',
                'phone'     => '78 200 00 02',
                'status'    => 'active',
                'password'  => $password,
                'reference' => 'MA-002',
            ]);

            Link::create(['manager_id' => $manager1->id, 'entity_id' => $pharmacie->id, 'is_admin' => true]);
            Link::create(['manager_id' => $manager2->id, 'entity_id' => $couture->id, 'is_admin' => true]);

            // ╔══════════════════════════════════════╗
            // ║  5. USERS (Clients)                   ║
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
            // ║  6. CARD TYPES                        ║
            // ╚══════════════════════════════════════╝
            $bronze = CardType::create(['name' => 'Bronze', 'discount' => 5, 'status' => 'active']);
            $silver = CardType::create(['name' => 'Silver', 'discount' => 10, 'status' => 'active']);
            $gold   = CardType::create(['name' => 'Gold', 'discount' => 15, 'status' => 'active']);

            // ╔══════════════════════════════════════╗
            // ║  7. APP ORDERS                        ║
            // ╚══════════════════════════════════════╝
            $appOrder1 = AppOrder::create([
                'entity_id' => $pharmacie->id,
                'name'      => 'Commande cartes Pharmacie Mame Diarra',
                'amount'    => 50000,
                'infos'     => 'Lot de 100 cartes de fidélité personnalisées',
                'status'    => 'completed',
                'reference' => 'SHP-001',
            ]);

            $appOrder2 = AppOrder::create([
                'entity_id' => $couture->id,
                'name'      => 'Commande cartes Ndèye Fatou Couture',
                'amount'    => 75000,
                'infos'     => 'Lot de 150 cartes de fidélité personnalisées',
                'status'    => 'completed',
                'reference' => 'SHP-002',
            ]);

            // ╔══════════════════════════════════════╗
            // ║  8. CARDS (4 total: 2 users × 2 shops)║
            // ╚══════════════════════════════════════╝
            $card1 = Card::create([
                'status'       => 'active',
                'entity_id'    => $pharmacie->id,
                'user_id'      => $user1->id,
                'card_type_id' => $bronze->id,
                'app_order_id' => $appOrder1->id,
                'credit'       => 1500,
            ]);

            $card2 = Card::create([
                'status'       => 'active',
                'entity_id'    => $couture->id,
                'user_id'      => $user1->id,
                'card_type_id' => $silver->id,
                'app_order_id' => $appOrder2->id,
                'credit'       => 2500,
            ]);

            $card3 = Card::create([
                'status'       => 'active',
                'entity_id'    => $pharmacie->id,
                'user_id'      => $user2->id,
                'card_type_id' => $silver->id,
                'app_order_id' => $appOrder1->id,
                'credit'       => 800,
            ]);

            $card4 = Card::create([
                'status'       => 'active',
                'entity_id'    => $couture->id,
                'user_id'      => $user2->id,
                'card_type_id' => $gold->id,
                'app_order_id' => $appOrder2->id,
                'credit'       => 3200,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  9. APP SUBSCRIPTIONS + PAYMENTS       ║
            // ╚══════════════════════════════════════╝
            $sub1 = AppSuscription::create([
                'entity_id'  => $pharmacie->id,
                'pricing_id' => $pro->id,
                'created_at' => '2025-12-30 10:00:00',
            ]);

            $sub2 = AppSuscription::create([
                'entity_id'  => $couture->id,
                'pricing_id' => $pro->id,
                'created_at' => '2025-12-30 10:00:00',
            ]);

            AppPayment::create([
                'amount'              => 24900,
                'paid_by'             => 'Moussa Diop',
                'status'              => 'completed',
                'app_suscription_id'  => $sub1->id,
                'app_order_id'        => $appOrder1->id,
            ]);

            AppPayment::create([
                'amount'              => 24900,
                'paid_by'             => 'Aminata Sall',
                'status'              => 'completed',
                'app_suscription_id'  => $sub2->id,
                'app_order_id'        => $appOrder2->id,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  10. DISCOUNTS                        ║
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
                'entity_id'       => $couture->id,
                'card_id'         => $card2->id,
                'discount_type'   => 'fixed',
                'discount_value'  => null,
                'discount_amount' => 2000,
                'expiration'      => '2026-06-30',
            ]);

            // ╔══════════════════════════════════════╗
            // ║  11. ORDERS (commandes clients)        ║
            // ╚══════════════════════════════════════╝
            // -- Pharmacie orders --
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
                'discount_id' => $discount1->id,
            ]);

            // -- Couture orders --
            $order4 = Order::create([
                'name'        => 'Boubou brodé homme',
                'description' => 'Boubou en bazin riche brodé, taille L',
                'price'       => 35000,
                'status'      => 'completed',
                'amount'      => 35000,
                'discount'    => 2000,
                'total'       => 33000,
                'discount_id' => $discount2->id,
            ]);

            $order5 = Order::create([
                'name'        => 'Robe Thioup femme',
                'description' => 'Robe en wax, modèle évasé, taille M',
                'price'       => 18000,
                'status'      => 'completed',
                'amount'      => 18000,
                'discount'    => 2000,
                'total'       => 16000,
                'discount_id' => $discount2->id,
            ]);

            $order6 = Order::create([
                'name'        => 'Ensemble enfant Tabaski',
                'description' => 'Ensemble bazin brodé enfant, taille 8 ans',
                'price'       => 15000,
                'status'      => 'pending',
                'amount'      => 15000,
                'discount'    => 0,
                'total'       => 15000,
                'discount_id' => $discount2->id,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  12. CARD CREDITS                     ║
            // ╚══════════════════════════════════════╝
            CardCredit::create(['card_id' => $card1->id, 'order_id' => $order1->id, 'amount' => 2250, 'credit' => 225]);
            CardCredit::create(['card_id' => $card1->id, 'order_id' => $order2->id, 'amount' => 3420, 'credit' => 342]);
            CardCredit::create(['card_id' => $card3->id, 'order_id' => $order3->id, 'amount' => 4500, 'credit' => 450]);
            CardCredit::create(['card_id' => $card2->id, 'order_id' => $order4->id, 'amount' => 33000, 'credit' => 1650]);
            CardCredit::create(['card_id' => $card2->id, 'order_id' => $order5->id, 'amount' => 16000, 'credit' => 800]);
            CardCredit::create(['card_id' => $card4->id, 'order_id' => $order6->id, 'amount' => 15000, 'credit' => 750]);

            // ╔══════════════════════════════════════╗
            // ║  13. ALERT APPS                       ║
            // ╚══════════════════════════════════════╝
            AlertApp::create([
                'entity_id'   => $pharmacie->id,
                'title'       => 'Nouveau client fidélité',
                'description' => 'Ibrahima Ndiaye vient de s\'inscrire au programme de fidélité.',
                'read'        => false,
                'card_id'     => $card1->id,
                'manager_id'  => $manager1->id,
            ]);

            AlertApp::create([
                'entity_id'   => $pharmacie->id,
                'title'       => 'Seuil de points atteint',
                'description' => 'La carte de Fatou Ba a atteint 800 points. Proposez-lui un passage Silver.',
                'read'        => true,
                'card_id'     => $card3->id,
                'manager_id'  => $manager1->id,
            ]);

            AlertApp::create([
                'entity_id'   => $couture->id,
                'title'       => 'Commande importante',
                'description' => 'Ibrahima Ndiaye a passé une commande de 35 000 FCFA.',
                'read'        => false,
                'card_id'     => $card2->id,
                'manager_id'  => $manager2->id,
            ]);

            // ╔══════════════════════════════════════╗
            // ║  14. ALERT MESSAGES                   ║
            // ╚══════════════════════════════════════╝
            AlertMessage::create([
                'title'       => 'Bienvenue sur Kolere !',
                'image'       => null,
                'description' => 'Votre boutique Pharmacie Mame Diarra est maintenant active. Commencez à fidéliser vos clients dès maintenant.',
                'entity_id'   => $pharmacie->id,
                'read'        => true,
            ]);

            AlertMessage::create([
                'title'       => 'Promotion Tabaski',
                'image'       => null,
                'description' => 'Pensez à créer vos promotions spéciales Tabaski pour booster vos ventes !',
                'entity_id'   => $couture->id,
                'read'        => false,
            ]);

            AlertMessage::create([
                'title'       => 'Abonnement bientôt expiré',
                'image'       => null,
                'description' => 'Votre abonnement Pro expire le 30 mars 2026. Renouvelez-le pour ne pas perdre vos avantages.',
                'entity_id'   => $pharmacie->id,
                'read'        => false,
            ]);
        });
    }
}
