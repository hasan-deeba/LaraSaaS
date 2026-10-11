<?php

return [

    'common' => [
        'save'   => 'Enregistrer',
        'cancel' => 'Annuler',
        'delete' => 'Supprimer',
        'dismiss'=> 'Ignorer',
        'close'  => 'Fermer',
    ],

    'dashboard' => [
        'title'          => 'Tableau de bord',
        'greeting'       => 'Bon retour, :name',
        'plan'           => 'Formule',
        'status'         => 'Statut',
        'manage_billing' => 'Gérer la facturation',
        'upgrade'        => 'Passer au niveau supérieur',
        'trial_until'    => 'Essai jusqu’au :date',
        'beta_ready'     => 'Activé pour vous',
    ],

    'pricing' => [
        'title'          => 'Tarifs',
        'subtitle'       => 'Commencez gratuitement, évoluez à votre rythme. Résiliable à tout moment.',
        'monthly'        => 'Mensuel',
        'yearly'         => 'Annuel',
        'per_monthly'    => 'mois',
        'per_yearly'     => 'an',
        'per_month_short'=> 'mois',
        'billed_yearly'  => 'facturé annuellement',
        'most_popular'   => 'Le plus populaire',
        'current_plan'   => 'Formule actuelle',
        'switch_plan'    => 'Changer de formule',
        'get_started'    => 'Commencer',
        'contact_sales'  => 'Parler aux ventes',
        'custom'         => 'Sur mesure',
        'trial'          => '{days} jours d’essai gratuit',
        'footnote'       => 'Prix en {currency}. Taxes en sus. Paiement sécurisé par Stripe.',
    ],

    'billing' => [
        'title'                => 'Facturation',
        'subtitle'             => 'Gérez votre abonnement, vos moyens de paiement et vos factures.',
        'status'               => 'Statut',
        'active'               => 'Actif',
        'free'                 => 'Gratuit',
        'no_subscription'      => 'Aucun abonnement actif',
        'subscription_required'=> 'Un abonnement est requis pour accéder à cette section.',
        'plan_required'        => 'Votre formule actuelle n’inclut pas cette fonctionnalité.',
        'missing_price'        => 'La formule :plan n’a pas de prix Stripe configuré pour :interval.',
        'portal'               => 'Portail client',
        'cancel'               => 'Résilier l’abonnement',
        'resume'               => 'Réactiver l’abonnement',
        'canceled'             => 'Abonnement résilié — l’accès reste actif jusqu’à la fin de la période.',
        'checkout_canceled'    => 'Paiement annulé — aucun débit n’a été effectué.',
        'success'              => 'Votre abonnement est actif. Bienvenue !',
        'on_trial'             => 'Essai',
        'cancels_on'           => 'Résiliation le :date',
        'ends_on'              => 'Se termine le :date',
        'resumed'              => 'Abonnement réactivé. Bon retour !',
        'swapped'              => 'Formule mise à jour avec succès.',
        'change_plan'          => 'Changer de formule',
        'invoices'             => 'Factures',
        'no_invoices'          => 'Aucune facture pour le moment.',
        'date'                 => 'Date',
        'amount'               => 'Montant',
    ],

    'announcements' => [
        'created' => 'Annonce diffusée.',
        'updated' => 'Annonce mise à jour.',
        'deleted' => 'Annonce supprimée.',
    ],

    'flags' => [
        'unavailable' => 'Cette fonctionnalité n’est pas encore disponible pour votre compte.',
        'activated'   => 'Fonctionnalité :flag activée.',
        'deactivated' => 'Fonctionnalité :flag désactivée.',
    ],

    'embed' => [
        'not_authenticated' => 'Ce lien d’intégration est absent ou expiré. Demandez à l’application hôte d’en générer un nouveau via LaraSaaS::embedUrl().',
        'powered_by'        => 'Sécurisé par :app',
    ],
];
