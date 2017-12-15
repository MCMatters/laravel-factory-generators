## Laravel Factory Generators

Generate factories for all non-created factories of models.

### Installation

```bash
composer require mcmatters/laravel-factory-generators
```

Include the service provider within your `config/app.php` file.

```php
'providers' => [
    McMatters\FactoryGenerators\ServiceProvider::class,
]
```

Publish the configuration.

```bash
php artisan vendor:publish --provider="McMatters\FactoryGenerators\ServiceProvider"
```

Then open the `config/factory-generators.php` and configure paths where your models are locating.

### Advanced configuration

| Name                  | Description |
|-----------------------|-------------|
| folders               | `models` - path where models are locating<br>`factories` - path where factories are locating. |
| follow_subdirectories | Enable this option if your models are in subdirectories and you wish to keep the structure of the folders. For example, if your model has next namespace: `App\Models\User\Profile` it will generate in your factories folder subdirectory with name `User` including `ProfileFactory.php` file.<br>**NOTE:** If you enable this option, please specify the root namespace. |
| model_namespace       | Requires only, if you enabled option above. |
| types                 | An array of custom types for DBAL. For example: `'json' => 'string'`. |
| prefix                | Prefix for factory files. |
| suffix                | Suffix for factory files. |
| skip_columns          | An array of the global column names for skipping, for example, you may wish to skip `created_at` and `updated_at` columns in all models. |
| skip_models           | An array of the fully qualified model names for skipping. |
| skip_model_columns    | An associative array with skipping columns for specific model. Example: `'App\Models\User' => ['password', 'remember_token']`. |
| align_array_keys      | If this option will be enabled, all your factories will include aligned array keys. |

## Usage

Just run the command `php artisan factory:generate`.
