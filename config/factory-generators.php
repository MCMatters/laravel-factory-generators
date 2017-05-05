<?php

declare(strict_types = 1);

return [
    'folders'            => [
        'models'    => base_path('app/Models'),
        'factories' => database_path('factories'),
    ],
    // Register custom types from DBAL.
    'types'              => [
        //
    ],
    // Prefix for factory files.
    'prefix'             => '',
    // Suffix for factory files.
    'suffix'             => 'Factory',
    // Global columns for skipping, for example, you may wish to skip
    // "created_at" and "updated_at" columns in all models.
    'skip_columns'       => [],
    // Skip models.
    'skip_models'        => [],
    // An associative array with skipping columns for specific model.
    // Example: "App\Models\User" => ["password", "remember_token"]
    'skip_model_columns' => [],
];
