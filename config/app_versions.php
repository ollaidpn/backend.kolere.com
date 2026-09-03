<?php

return [
    'ios' => [
        'versions' => [
            [
                'version' => '1.0.0',
                'must_update' => false,
                'changelog' => 'Version initiale de l\'application.',
            ],
            [
                'version' => '2.0.2',
                'must_update' => false,
                'changelog' => "Amélioration des performances, stabilité et nouvelles fonctionnalités.",
            ],
        ],
    ],
    'android' => [
        'versions' => [
            [
                'version' => '1.0.0',
                'must_update' => false,
                'changelog' => 'Version initiale de l\'application.',
            ],
            [
                'version' => '2.0.3',
                'must_update' => true,
                'changelog' => "Amélioration des performances, stabilité et nouvelles fonctionnalités.",
            ],
        ],
    ],
];
