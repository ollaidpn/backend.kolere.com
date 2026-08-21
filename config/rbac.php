<?php

return [
    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire le tableau de bord'],
            ],
        ],
        'stats' => [
            'label' => 'Statistiques',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les statistiques'],
                ['action' => 'export', 'label' => 'Exporter les statistiques'],
            ],
        ],
        'clients' => [
            'label' => 'Clients',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les clients'],
                ['action' => 'view_details', 'label' => 'Voir les détails'],
                ['action' => 'create', 'label' => 'Créer un client'],
                ['action' => 'update', 'label' => 'Modifier un client'],
                ['action' => 'delete', 'label' => 'Supprimer un client'],
                ['action' => 'assign_card', 'label' => 'Assigner une carte'],
                ['action' => 'export', 'label' => 'Exporter les clients'],
            ],
        ],
        'cards' => [
            'label' => 'Cartes',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les cartes'],
                ['action' => 'create', 'label' => 'Créer une carte'],
                ['action' => 'update', 'label' => 'Modifier une carte'],
                ['action' => 'delete', 'label' => 'Supprimer une carte'],
                ['action' => 'scan', 'label' => 'Scanner une carte'],
                ['action' => 'block', 'label' => 'Bloquer une carte'],
                ['action' => 'unblock', 'label' => 'Débloquer une carte'],
                ['action' => 'credit', 'label' => 'Créditer des points'],
                ['action' => 'debit', 'label' => 'Débiter des points'],
            ],
        ],
        'sales' => [
            'label' => 'Ventes',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les ventes'],
                ['action' => 'view_details', 'label' => 'Voir le détail'],
                ['action' => 'create', 'label' => 'Créer une vente'],
                ['action' => 'update', 'label' => 'Modifier une vente'],
                ['action' => 'delete', 'label' => 'Supprimer une vente'],
                ['action' => 'export', 'label' => 'Exporter les ventes'],
            ],
        ],
        'rewards' => [
            'label' => 'Récompenses',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les récompenses'],
                ['action' => 'create', 'label' => 'Créer une récompense'],
                ['action' => 'update', 'label' => 'Modifier une récompense'],
                ['action' => 'delete', 'label' => 'Supprimer une récompense'],
                ['action' => 'activate', 'label' => 'Activer une récompense'],
                ['action' => 'deactivate', 'label' => 'Désactiver une récompense'],
            ],
        ],
        'conversions' => [
            'label' => 'Conversions',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les conversions'],
                ['action' => 'create', 'label' => 'Créer une conversion'],
                ['action' => 'update', 'label' => 'Modifier une conversion'],
                ['action' => 'delete', 'label' => 'Supprimer une conversion'],
                ['action' => 'approve', 'label' => 'Valider une conversion'],
                ['action' => 'reject', 'label' => 'Rejeter une conversion'],
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les notifications'],
                ['action' => 'create', 'label' => 'Créer une notification'],
                ['action' => 'update', 'label' => 'Modifier une notification'],
                ['action' => 'delete', 'label' => 'Supprimer une notification'],
                ['action' => 'send', 'label' => 'Envoyer une notification'],
                ['action' => 'mark_read', 'label' => 'Marquer comme lu'],
                ['action' => 'archive', 'label' => 'Archiver une notification'],
            ],
        ],
        'demandes' => [
            'label' => 'Demandes',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les demandes'],
                ['action' => 'view_details', 'label' => 'Voir les détails'],
                ['action' => 'update', 'label' => 'Modifier une demande'],
                ['action' => 'delete', 'label' => 'Supprimer une demande'],
                ['action' => 'approve', 'label' => 'Approuver une demande'],
                ['action' => 'reject', 'label' => 'Rejeter une demande'],
            ],
        ],
        'shop.orders' => [
            'label' => 'Boutique / Commandes',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les commandes'],
                ['action' => 'view_details', 'label' => 'Voir les détails'],
                ['action' => 'update', 'label' => 'Modifier une commande'],
                ['action' => 'delete', 'label' => 'Supprimer une commande'],
                ['action' => 'export', 'label' => 'Exporter les commandes'],
            ],
        ],
        'shop.items' => [
            'label' => 'Boutique / Articles',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les articles'],
                ['action' => 'view_details', 'label' => 'Voir les détails'],
                ['action' => 'create', 'label' => 'Créer un article'],
                ['action' => 'update', 'label' => 'Modifier un article'],
                ['action' => 'delete', 'label' => 'Supprimer un article'],
                ['action' => 'publish', 'label' => 'Publier un article'],
                ['action' => 'archive', 'label' => 'Archiver un article'],
            ],
        ],
        'shop.categories' => [
            'label' => 'Boutique / Catégories',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les catégories'],
                ['action' => 'create', 'label' => 'Créer une catégorie'],
                ['action' => 'update', 'label' => 'Modifier une catégorie'],
                ['action' => 'delete', 'label' => 'Supprimer une catégorie'],
            ],
        ],
        'shop.brands' => [
            'label' => 'Boutique / Marques',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les marques'],
                ['action' => 'create', 'label' => 'Créer une marque'],
                ['action' => 'update', 'label' => 'Modifier une marque'],
                ['action' => 'delete', 'label' => 'Supprimer une marque'],
            ],
        ],
        'shop.promo_codes' => [
            'label' => 'Boutique / Code promo',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les codes promo'],
                ['action' => 'create', 'label' => 'Créer un code promo'],
                ['action' => 'update', 'label' => 'Modifier un code promo'],
                ['action' => 'delete', 'label' => 'Supprimer un code promo'],
                ['action' => 'activate', 'label' => 'Activer un code promo'],
                ['action' => 'deactivate', 'label' => 'Désactiver un code promo'],
            ],
        ],
        'shop.payments' => [
            'label' => 'Boutique / Paiements',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les paiements'],
                ['action' => 'configure', 'label' => 'Configurer le paiement'],
                ['action' => 'update', 'label' => 'Modifier les paiements'],
                ['action' => 'refund', 'label' => 'Rembourser'],
            ],
        ],
        'shop.payouts' => [
            'label' => 'Boutique / Retraits',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les retraits'],
                ['action' => 'create', 'label' => 'Créer un retrait'],
                ['action' => 'update', 'label' => 'Modifier un retrait'],
                ['action' => 'approve', 'label' => 'Approuver un retrait'],
                ['action' => 'reject', 'label' => 'Rejeter un retrait'],
            ],
        ],
        'settings.infos' => [
            'label' => 'Paramètres / Infos boutique',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les infos boutique'],
                ['action' => 'update', 'label' => 'Modifier les infos boutique'],
                ['action' => 'upload_logo', 'label' => 'Uploader le logo'],
                ['action' => 'configure_theme', 'label' => 'Configurer le thème'],
            ],
        ],
        'settings.payment' => [
            'label' => 'Paramètres / Paiement & API',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les paramètres paiement'],
                ['action' => 'update', 'label' => 'Modifier les paramètres paiement'],
                ['action' => 'configure_api', 'label' => 'Configurer les clés API'],
                ['action' => 'configure_webhook', 'label' => 'Configurer le webhook'],
            ],
        ],
        'settings.domain' => [
            'label' => 'Paramètres / Domaine',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire le domaine'],
                ['action' => 'update', 'label' => 'Modifier le domaine'],
                ['action' => 'request_activation', 'label' => 'Demander l’activation'],
            ],
        ],
        'settings.website' => [
            'label' => 'Paramètres / Website',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire le website'],
                ['action' => 'update', 'label' => 'Modifier le website'],
                ['action' => 'manage_slider', 'label' => 'Gérer le slider'],
                ['action' => 'manage_delivery', 'label' => 'Gérer la livraison'],
                ['action' => 'manage_theme', 'label' => 'Gérer le thème'],
            ],
        ],
        'settings.users' => [
            'label' => 'Paramètres / Utilisateurs',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les utilisateurs'],
                ['action' => 'create', 'label' => 'Créer un utilisateur'],
                ['action' => 'update', 'label' => 'Modifier un utilisateur'],
                ['action' => 'delete', 'label' => 'Supprimer un utilisateur'],
                ['action' => 'invite', 'label' => 'Inviter un utilisateur'],
                ['action' => 'activate', 'label' => 'Activer un utilisateur'],
                ['action' => 'deactivate', 'label' => 'Désactiver un utilisateur'],
                ['action' => 'reset_password', 'label' => 'Réinitialiser le mot de passe'],
                ['action' => 'assign_role', 'label' => 'Assigner un rôle'],
            ],
        ],
        'settings.roles' => [
            'label' => 'Paramètres / Rôles',
            'permissions' => [
                ['action' => 'read', 'label' => 'Lire les rôles'],
                ['action' => 'create', 'label' => 'Créer un rôle'],
                ['action' => 'update', 'label' => 'Modifier un rôle'],
                ['action' => 'delete', 'label' => 'Supprimer un rôle'],
                ['action' => 'assign_permissions', 'label' => 'Attribuer les permissions'],
            ],
        ],
    ],

    'default_roles' => [
        [
            'slug' => 'super_admin',
            'name' => 'Super Admin',
            'user_type' => 'admin',
            'scope' => 'global',
            'description' => 'Accès complet à la plateforme.',
            'is_system' => true,
        ],
        [
            'slug' => 'boutique_manager',
            'name' => 'Boutique Manager',
            'user_type' => 'shop',
            'scope' => 'global',
            'description' => 'Accès complet au backoffice de la boutique.',
            'is_system' => true,
        ],
        [
            'slug' => 'caissier',
            'name' => 'Caissier',
            'user_type' => 'shop',
            'scope' => 'global',
            'description' => 'Gestion de caisse, cartes, ventes et lecture des tableaux de bord.',
            'is_system' => true,
        ],
        [
            'slug' => 'catalogue_manager',
            'name' => 'Catalogue Manager',
            'user_type' => 'shop',
            'scope' => 'global',
            'description' => 'Gestion du catalogue, catégories, marques et promo codes.',
            'is_system' => true,
        ],
        [
            'slug' => 'support_marketing',
            'name' => 'Support Marketing',
            'user_type' => 'shop',
            'scope' => 'global',
            'description' => 'Notifications, demandes, conversions et récompenses.',
            'is_system' => true,
        ],
        [
            'slug' => 'lecture_seule',
            'name' => 'Lecture Seule',
            'user_type' => 'shop',
            'scope' => 'global',
            'description' => 'Consultation sans modification.',
            'is_system' => true,
        ],
    ],
];
