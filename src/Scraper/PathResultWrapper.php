<?php
namespace MuminovicAnel\OpenApiGenerator\Scraper;

use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use MuminovicAnel\OpenApiGenerator\InitableObject;

class PathResultWrapper extends InitableObject
{
    /**
     * @var string Class name of path result wrapper
     */
    public $wrapperClass;

    /**
     * @var string Name of wrapper class property which holds actual path result
     */
    public $resultingProperty;

    /**
     * @param Schema $result
     * @return Schema
     */
    public function wrapResultInSchema(Schema $result)
    {
        $property = new Property([
            'property' => $this->resultingProperty,
        ]);
        $property->mergeProperties($result);

        return new Schema([
            'properties' => [
                $property,
            ],
            'type' => 'object',
        ]);
    }
}
