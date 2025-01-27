<?php
namespace MuminovicAnel\OpenApiGenerator\Console;

use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MuminovicAnel\OpenApiGenerator\Generator\DefaultGenerator;
use MuminovicAnel\OpenApiGenerator\Generator\GeneratorResultSpecification;
use MuminovicAnel\OpenApiGenerator\ScraperSkeleton;

class GenerateCommand extends BasicCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'generate';

    protected static $defaultDescription = 'Generates openapi configurations';

    public function configure()
    {
        $scrapers = array_keys(ScraperSkeleton::getAllDefaultScrapers());
        $this
            ->setHelp('This command allows you to generate openapi-files for current application via user-defined scraper.')
            ->addOption('scraper', null, InputOption::VALUE_REQUIRED, 'The scraper class (e.g. App\components\OpenApiScraper) or file (e.g. src/app/components/OpenApiScraper.php) or scraper name (e.g. yii2). Default scrapers: '.implode('/', $scrapers))
//            ->addOption('generator', 'g', InputOption::VALUE_REQUIRED, 'The generator class or file', DefaultGenerator::class)
            ->addOption('specification', null, InputOption::VALUE_REQUIRED, 'Pattern for specifications', '.+')
            ->addOption('specification-title', null, InputOption::VALUE_REQUIRED, 'Title', ScraperSkeleton::$specificationTitle)
            ->addOption('specification-description', null, InputOption::VALUE_REQUIRED, 'Description', ScraperSkeleton::$specificationDescription)
            ->addOption('specification-version', null, InputOption::VALUE_REQUIRED, 'Version', ScraperSkeleton::$specificationVersion)
            ->addArgument('directory', InputArgument::OPTIONAL, 'Folder to start analysis', getcwd())
            ->addArgument('output', InputArgument::OPTIONAL, 'Folder for output files (or `-- --` for output)', getcwd())
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Format of output: json or yaml', 'yaml')
            ->addOption('inspect', null, InputOption::VALUE_OPTIONAL, 'Probe run - prints result of generation instead of saving to disk. Pass endpoint name to filter (with mask)', false)
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setUpStyles($output);
        $scraper = $input->getOption('scraper');
        if (empty($scraper)) {
            throw new \InvalidArgumentException('Set a scraper');
        }

        $scraper = $this->createScraper($scraper, $output);
        $scraper->specificationPattern = $input->getOption('specification');

        $generator = $this->createGenerator(DefaultGenerator::class, $output);
        $result = $generator->generate($scraper, $input->getArgument('directory'));

        $inspect_mode = $input->getOption('inspect');

        if ($inspect_mode !== false) {
            $this->inspect($input, $output, $result, $inspect_mode);
        } else {
            $this->generate($input, $output, $result);
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $result
     * @return void
     * @throws \Exception
     */
    public function generate(InputInterface $input, OutputInterface $output, array $result)
    {
        $output_dir = rtrim($input->getArgument('output'), '/');
        if ($output_dir !== '--') {
            if (!is_dir($output_dir)) {
                if (is_file($output_dir)) throw new \InvalidArgumentException($output_dir.' is not a folder');
                else if (!mkdir($output_dir, 0777, true)) throw new \InvalidArgumentException($output_dir.' could not be created');
            } else if (!is_writable($output_dir) && !chmod($output_dir, 0777)) throw new \InvalidArgumentException($output_dir.' could not be set writable');
        }

        $output_format = $input->getOption('format');

        /** @var GeneratorResultSpecification $specification */
        foreach ($result as $specification) {
            if ($output_dir === '--') {
                $output->write(
                    $output_format === 'yaml'
                        ? $specification->specification->toYaml()
                        : $specification->specification->toJson()
                );
            } else {
                $specification_file = $output_dir.'/'.$specification->id.'.'.$output_format;
                $output->write('Writing ' . $specification->id . ' to ' . $specification_file . ' ... ');
                $specification->specification->saveAs($specification_file);
                $output->writeln('ok');
            }
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $result
     * @return int
     */
    public function inspect(InputInterface $input, OutputInterface $output, array $result, ?string $inspectFilter)
    {
        switch (count($result)) {
            case 0:
                $output->writeln('No available specifications');
                break;
            case 1:
                $this->printSpecification($output, null, $result[0], $inspectFilter);
                break;
            default:
                $output->writeln('Total '.count($result).' specification(s)');
                foreach ($result as $specification) {
                    $this->printSpecification($output, $specification->title.' '.$specification->id, $specification, $inspectFilter);
                }
                break;
        }
        return 0;
    }

    protected function printSpecification(
        OutputInterface $output,
        ?string $title,
        GeneratorResultSpecification $specification,
        ?string $pathFilter)
    {
        if (!empty($title)) {
            $output->writeln($title);
        }



        /** @var PathItem $path */
        foreach ($specification->specification->paths as $pathId => $path) {
            if ($pathFilter !== null && fnmatch($pathFilter, $path->path) === false) {
                continue;
            }
            foreach (['get', 'post'] as $method) {
                if (!isset($path->{$method}) || $path->{$method} === \OpenApi\Annotations\UNDEFINED) {
                    continue;
                }

                $table = new Table($output);
                $table->setStyle('box');
//                $table->setHeaders(['path', 'method', 'descripton', 'parameters']);

                /** @var Operation $path_method */
                $path_method = $path->{$method};
                $table->setHeaders([
                    $method,
                    $path->path,
                    $path_method->summary !== \OpenApi\Annotations\UNDEFINED
                        ? $path_method->summary
                        : null
                ]);
                $path_description = $path->{$method}->description !== \OpenApi\Annotations\UNDEFINED
                    ? trim($path->{$method}->description)
                    : null;
                if (!empty($path_description)) {
                    $table->addRow([new TableCell($path->{$method}->description, ['colspan' => 3])]);
                }

                if (
                    !empty($path_description)
                    && ($path_method->parameters !== \OpenApi\Annotations\UNDEFINED
                        && count($path_method->parameters) > 0)
                ) {
                    $table->addRow(new TableSeparator());
                }

//                $path->post->security

                if (
                    $path_method->parameters !== \OpenApi\Annotations\UNDEFINED
                    && count($path_method->parameters) > 0
                ) {
                    $table->addRow([new TableCell('Parameters (' . count($path_method->parameters) . ')', ['colspan' => 3])]);
                    /** @var Parameter $path_parameter */
                    foreach ($path_method->parameters as $path_parameter) {
                        $table->addRow([
                            $path_parameter->schema !== \OpenApi\Annotations\UNDEFINED ? $path_parameter->schema->type : null,
                            $path_parameter->name,
                            $path_parameter->description !== \OpenApi\Annotations\UNDEFINED ? $path_parameter->description : null,
                        ]);
                    }
                }

                $resultTableRows = [];
                if (
                    $path_method->responses !== \OpenApi\Annotations\UNDEFINED
                    && isset($path_method->responses[0]->content[0])
                    && $path_method->responses[0]->content[0]->schema !== \OpenApi\Annotations\UNDEFINED
                    && $this->compressSchema($path_method->responses[0]->content[0]->schema, $resultTableRows)
                ) {
                    if (!empty($path_description)
                        || ($path_method->parameters !== \OpenApi\Annotations\UNDEFINED
                            && count($path_method->parameters) > 0)) {
                        $table->addRow(new TableSeparator());
                    }
                    $table->addRow([new TableCell('Result (' . count($resultTableRows) . ')', ['colspan' => 3])]);
                    $table->addRows($resultTableRows);
                }

                $table->render();
            }
        }
    }

    protected function appendTableWithSchema(Table $table, array $schema, int $level = 0)
    {
        foreach ($schema as $name => $value) {
            if (is_scalar($value)) {
                $table->addRow([str_repeat('  ', $level) . $name, $value]);
            } else {
                $table->addRow([str_repeat('  ', $level) . $name . ':']);
                $this->appendTableWithSchema($table, $value, $level + 1);
            }
        }
    }

    protected function isScalarSchema(Schema $schema)
    {
        switch ($schema->type) {
            case 'object':
            case 'array':
                return false;
            default:
                return true;
        }
    }

    protected function getScalarTitle(Schema $schema)
    {
        return $schema->type;
    }

    protected function compressSchema(Schema $schema, &$tableRows = [], ?string $prefix = null)
    {
        $schema_description = ($schema->description !== \OpenApi\UNDEFINED)
            ? trim($schema->description)
            : null;
        switch ($schema->type) {
            case 'array':
                $array_name = $schema instanceof Property ? $schema->property : null;
                if ($schema->items === null) {
                    $tableRows[] = [$prefix . $array_name, 'array', $schema_description];
                } else {
                    if ($this->isScalarSchema($schema->items)) {
                        $tableRows[] = [$prefix . $array_name, 'array of ' . $this->getScalarTitle($schema->items), $schema_description];
                    } else {
                        $tableRows[] = [$prefix . $array_name, 'array of', $schema_description];
                        $this->compressSchema($schema->items, $tableRows, $prefix . $array_name . '[*].');
                    }
                }
                break;

            case 'object':
                if ($schema instanceof Property) {
                    $tableRows[] = [
                        $prefix . $schema->property,
                        ($schema->nullable === true ? '?' : null) . 'object',
                        $schema_description
                    ];
                }
                if ($schema->properties !== \OpenApi\UNDEFINED) {
                    foreach ($schema->properties as $property) {
                        $this->compressSchema($property, $tableRows, $prefix . ($schema instanceof Property ? $schema->property . '.' : null));
                    }
                }
                return $tableRows;

            default:
                if ($schema->allOf !== \OpenApi\UNDEFINED) {
                    /** @var Schema $schemaListItem */
                    foreach ($schema->allOf as $schemaListItem) {
                        $this->compressSchema($schemaListItem, $tableRows, $prefix . ($schema instanceof Property ? $schema->property . '.' : null));
                    }
                } else if ($schema->type !== \OpenApi\UNDEFINED) {
                    $tableRows[] = [
                        $prefix . $schema->property,
                        ($schema->nullable === true ? '?' : null) . $schema->type,
                        $schema_description
                    ];
                } else if ($schema->type === \OpenApi\UNDEFINED) {
                    return false;
                }
        }
        return true;
    }
}
