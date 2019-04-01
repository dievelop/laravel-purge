<?php

return [
    'defaults' => [
        'extensions_blacklist' => [
            '.gitignore',
            '.gitkeep',
        ],
        'extensions' => [
            '.xyz'
        ],
        'directories' => '/',
        'recursive' => false,
        'minutes_old' => 60 * 24 * 365, // 1 year
        'delete_empty_directory' => false,
    ],

    'disks' => [
//        'logs' => [
//            'disk' => 'local',
//            'directories' => ['/*/filters/', '/*/products/'],
//            'recursive' => false,
//            'extensions' => ['.xyz'],
//            'extensions_blacklist' => ['.xyz'],
//            'minutes_old' => 60 * 24 * 30,
//            'delete_empty_directory' => false,
//        ],
    ],
];
