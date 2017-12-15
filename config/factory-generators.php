<?php

declare(strict_types = 1);

return [
    'folders'               => [
        'models'    => $app->basePath().'/app/Models',
        'factories' => $app->databasePath('factories'),
    ],

    // Enable this option if your models are in subdirectories
    // and you wish to keep the structure of the folders.
    // For example, if your model has next namespace: "App\Models\User\Profile"
    // it will generate in your factories folder subdirectory with name "User"
    // including "ProfileFactory.php" file.
    //
    // NOTE: If you enable this option, please specify the root namespace.
    'follow_subdirectories' => false,

    // Requires only, if you enabled option above.
    'model_namespace'       => 'App\\Models',

    // Register custom types for DBAL.
    'types'                 => [
        //
    ],

    // Prefix for factory files.
    'prefix'                => '',

    // Suffix for factory files.
    'suffix'                => 'Factory',

    // Global columns for skipping, for example, you may wish to skip
    // "created_at" and "updated_at" columns in all models.
    'skip_columns'          => [],

    // Skip models.
    'skip_models'           => [],

    // An associative array with skipping columns for specific model.
    // Example: "App\Models\User" => ["password", "remember_token"]
    'skip_model_columns'    => [],

    // Option for align array key pairs.
    'align_array_keys'      => false,
];
