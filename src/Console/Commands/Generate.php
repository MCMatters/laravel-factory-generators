<?php

declare(strict_types=1);

namespace McMatters\FactoryGenerators\Console\Commands;

use Composer\Autoload\ClassMapGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\{ Container\Container, Filesystem\FileNotFoundException};
use Illuminate\Database\Eloquent\{Factory, Model, Relations\Pivot};
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function class_basename, file_get_contents, file_put_contents, implode,
    in_array, is_dir, max, mkdir, rtrim, strlen, str_repeat, str_replace, substr;

use const false, null, true, DIRECTORY_SEPARATOR;

/**
 * Class Generate
 *
 * @package McMatters\FactoryGenerators\Console\Commands
 */
class Generate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'factory:generate';

    /**
     * @var string
     */
    protected $description = 'Generate factories for models';

    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var string
     */
    protected $stub;

    /**
     * Generate constructor.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->config = $app->make('config');
        $this->stub = $this->getStubContent();

        parent::__construct();
    }

    /**
     * @return void
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle()
    {
        $models = $this->getModels();
        $notDefined = $this->getNotDefinedModels($models);
        $mappedModels = $this->mapFakeData($notDefined);
        $this->publishFactories($mappedModels);
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function getModels(): array
    {
        $models = [];
        $dir = $this->config->get('factory-generators.folders.models');
        $skipModels = $this->config->get('factory-generators.skip_models');

        foreach (ClassMapGenerator::createMap($dir) as $model => $path) {
            try {
                $reflection = new ReflectionClass($model);
            } catch (ReflectionException $e) {
                continue;
            }

            if (
                $reflection->isInstantiable() &&
                $reflection->isSubclassOf(Model::class) &&
                !$reflection->isSubclassOf(Pivot::class) &&
                !in_array($model, $skipModels, true)
            ) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * @param array $models
     *
     * @return array
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getNotDefinedModels(array $models): array
    {
        $notDefined = [];
        $factory = $this->app->make(Factory::class);

        foreach ($models as $model) {
            if (!$factory->offsetExists($model)) {
                $notDefined[] = $model;
            }
        }

        return $notDefined;
    }

    /**
     * @param array $models
     *
     * @return array
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function mapFakeData(array $models): array
    {
        $modelColumns = [];
        $manager = $this->app->make('db')->getDoctrineSchemaManager();
        $skipColumns = $this->config->get('factory-generators.skip_columns');
        $skipModelColumns = $this->config->get('factory-generators.skip_model_columns');

        foreach ($models as $model) {
            $table = (new $model)->getTable();
            /** @var array $columns */
            $columns = $manager->tryMethod('listTableColumns', $table);

            if (!$columns) {
                continue;
            }

            /** @var \Doctrine\DBAL\Schema\Column $column */
            foreach ($columns as $column) {
                $columnName = $column->getName();

                // Skip auto incrementing or skipped from config columns.
                if (
                    $column->getAutoincrement() ||
                    in_array($columnName, $skipColumns, true) ||
                    (
                        isset($skipModelColumns[$model]) &&
                        in_array($columnName, $skipModelColumns[$model], true)
                    )
                ) {
                    continue;
                }

                $type = $this->getMappedType($column->getType()->getName());
                $mappedData = $this->getMappedFakeData($type);
                $modelColumns[$model][$columnName] = $mappedData;
            }
        }

        return $modelColumns;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getMappedType(string $type): string
    {
        static $types;

        if (null === $types) {
            $types = $this->config->get('factory-generators.types') + [
                'smallint' => 'boolean',
                'bigint' => 'integer',
                'datetimetz' => 'datetime',
                'decimal' => 'float',
                'binary' => 'text',
                'blob' => 'text',
                'json_array' => 'json',
                'simple_array' => 'json',
                'object' => 'json',
            ];
        }

        return $types[$type] ?? $type;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getMappedFakeData(string $type): string
    {
        $map = [
            'integer' => '$faker->numberBetween(1, 100)',
            'string' => '$faker->text(50)',
            'text' => '$faker->text()',
            'boolean' => '(int) $faker->boolean',
            'float' => '$faker->randomFloat(2, 0, 100)',
            'datetime' => '$faker->dateTime',
            'date' => '$faker->date()',
            'time' => '$faker->time()',
            'json' => 'array_fill(0, 10, $faker->unique()->realText())',
        ];

        return $map[$type] ?? 'null';
    }

    /**
     * @param array $models
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function publishFactories(array $models)
    {
        $config = $this->config->get('factory-generators');
        $factoryPath = rtrim(Arr::get($config, 'folders.factories'), '/').DIRECTORY_SEPARATOR;

        foreach ($models as $model => $data) {
            $path = $factoryPath;
            $content = $this->replaceStub(
                $model,
                $this->getContentAttributes($data)
            );

            $modelBaseName = class_basename($model);
            $fileName = "{$config['prefix']}{$modelBaseName}{$config['suffix']}.php";

            if ($config['follow_subdirectories']) {
                $partNamespace = substr($model, strlen($config['model_namespace']) + 1);
                $subNamespace = substr($partNamespace, 0, -strlen($modelBaseName) - 1);
                $path .= str_replace('\\', DIRECTORY_SEPARATOR, $subNamespace);

                $this->makeSubdirectories($path);
            }

            file_put_contents("{$path}/{$fileName}", $content);
        }
    }

    /**
     * @param array $content
     *
     * @return string
     */
    protected function getContentAttributes(array $content): string
    {
        $attributes = [];

        if ($this->config->get('factory-generators.align_array_keys', false)) {
            $max = 0;

            foreach ($content as $key => $attribute) {
                $max = max($max, strlen($key));
            }

            foreach ($content as $key => $attribute) {
                $gaps = str_repeat(' ', $max - strlen($key));
                $attributes[] = "\t\t'{$key}'{$gaps} => {$attribute},";
            }
        } else {
            foreach ($content as $key => $attribute) {
                $attributes[] = "\t\t'{$key}' => {$attribute},";
            }
        }

        return implode("\n", $attributes);
    }

    /**
     * @param string $model
     * @param string $attributes
     *
     * @return string
     */
    protected function replaceStub(string $model, string $attributes): string
    {
        return str_replace(
            ['{DummyClass}', '{DummyAttributes}'],
            [$model, $attributes],
            $this->stub
        );
    }

    /**
     * @param string $path
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function makeSubdirectories(string $path)
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true, true)) {
            throw new RuntimeException("Cannot create subdirectory {$path}");
        }
    }

    /**
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getStubContent(): string
    {
        $stub = file_get_contents($this->getStubFolderPath().'/factory.stub');

        if (false === $stub) {
            throw new FileNotFoundException(
                'There is a problem with getting stub content.'
            );
        }

        return $stub;
    }

    /**
     * @return string
     */
    protected function getStubFolderPath(): string
    {
        return __DIR__.'/../../../stubs';
    }
}
