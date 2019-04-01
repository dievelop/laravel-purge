<?php

return [
    'defaults' => [
        'extensions_blacklist' => [
            '.gitignore',
            '.gitkeep',
        ],
        'extensions' => [
            '.php',
        ],
        'directories' => '/',
        'recursive' => false,
        'minutes_old' => 60 * 24 * 365, // 1 year
        'delete_empty_directory' => false,
    ],

    'disks' => [
        'config_key' => [
            'disk' => 'local',
            'directories' => ['directory_1', 'directory_2'],
            'extensions' => ['.xyz'],
            'extensions_blacklist' => ['.abc'],
            'minutes_old' => 10,
            'delete_empty_directory' => false,
            'recursive' => false,
        ],
    ],
];
