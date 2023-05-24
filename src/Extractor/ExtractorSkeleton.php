<?php
namespace MuminovicAnel\OpenApiGenerator\Extractor;

use MuminovicAnel\OpenApiGenerator\Generator\DefaultGenerator;

abstract class ExtractorSkeleton extends \MuminovicAnel\OpenApiGenerator\ErrorableObject
{
    /** @var DefaultGenerator */
    protected DefaultGenerator $generator;

    public function __construct(DefaultGenerator $generator)
    {
        $this->generator = $generator;
    }

    abstract public function extract(\ReflectionMethod $method, \ReflectionParameter $parameter, &$required = []);
}
