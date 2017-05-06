<?php

declare(strict_types = 1);

namespace McMatters\FactoryGenerators\Console\Commands;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\{
    Factory, Model, Relations\Pivot
};
use Illuminate\Support\Facades\{
    DB, File
};
use ReflectionClass;
use ReflectionException;
use Symfony\Component\ClassLoader\ClassMapGenerator;

/**
 * Class Generate
 *
 * @package McMatters\FactoryGenerators
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
     * @var string
     */
    protected $stub;

    /**
     * Generate constructor.
     */
    public function __construct()
    {
        $this->stub = file_get_contents(__DIR__.'/../../../stubs/factory.stub');
        parent::__construct();
    }

    /**
     * Run command.
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
     */
    protected function getModels(): array
    {
        $models = [];
        $dir = config('factory-generators.folders.models');
        $skipModels = config('factory-generators.skip_models');

        foreach (ClassMapGenerator::createMap($dir) as $model => $path) {
            try {
                $reflection = new ReflectionClass($model);
            } catch (ReflectionException $e) {
                continue;
            }

            if ($reflection->isInstantiable() &&
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
     */
    protected function getNotDefinedModels(array $models): array
    {
        $notDefined = [];
        $factory = app(Factory::class);

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
     */
    protected function mapFakeData(array $models): array
    {
        $modelColumns = [];
        $manager = DB::getDoctrineSchemaManager();
        $skipColumns = config('factory-generators.skip_columns');
        $skipModelColumns = config('factory-generators.skip_model_columns');

        foreach ($models as $model) {
            $table = (new $model)->getTable();
            /** @var array $columns */
            $columns = $manager->tryMethod('listTableColumns', $table);

            if (!$columns) {
                continue;
            }

            /** @var Column $column */
            foreach ($columns as $column) {
                $columnName = $column->getName();
                if ($column->getAutoincrement() ||
                    in_array($columnName, $skipColumns, true) ||
                    (isset($skipModelColumns[$model]) &&
                        in_array($columnName, $skipModelColumns[$model], true))
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
            $types = array_merge(
                [
                    'smallint'     => 'boolean',
                    'bigint'       => 'integer',
                    'datetimetz'   => 'datetime',
                    'decimal'      => 'float',
                    'binary'       => 'text',
                    'blob'         => 'text',
                    'json_array'   => 'json',
                    'simple_array' => 'json',
                    'object'       => 'json',
                ],
                config('factory-generators.types')
            );
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
            'integer'  => '$faker->numberBetween(1, 100)',
            'string'   => '$faker->text(50)',
            'text'     => '$faker->text()',
            'boolean'  => '(int) $faker->boolean',
            'float'    => '$faker->randomFloat(2, 0, 100)',
            'datetime' => '$faker->dateTime',
            'date'     => '$faker->date()',
            'time'     => '$faker->time()',
            'json'     => 'array_fill(0, 10, $faker->unique()->realText())',
        ];

        return $map[$type] ?? 'null';
    }

    /**
     * @param array $models
     */
    protected function publishFactories(array $models)
    {
        $prefix = config('factory-generators.prefix');
        $suffix = config('factory-generators.suffix');

        foreach ($models as $model => $data) {
            $fileName = config('factory-generators.folders.factories').'/'.class_basename($model);
            $content = $this->replaceStub(
                $model,
                $this->getContentAttributes($data)
            );
            File::put("{$prefix}{$fileName}{$suffix}.php", $content);
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

        if (config('factory-generators.align_array_keys', false)) {
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
}
