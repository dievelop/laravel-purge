<?php

return [
    'extensions_blacklist' => [
        '.gitignore',
    ],

    'disks' => [
        'uploads' => [
            'directory' => '/',
            'recursive' => false,
            'extensions' => [],
            'minutes_old' => 60 * 24 * 30,
            'delete_empty_directory' => true,
        ],
    ],

    'cache' => [

    ],
];
