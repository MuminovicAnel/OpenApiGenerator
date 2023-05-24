<?php
namespace MuminovicAnel\OpenApiGenerator\Scraper\SecurityScheme;

class HttpSecurityScheme extends \MuminovicAnel\OpenApiGenerator\InitableObject
{
    /**
     * @var string ID of security scheme
     */
    public $id;

    /**
     * @var string Type of security scheme
     */
    public $type = 'http';

    /**
     * @var string basic or bearer
     */
    public $scheme;

    /**
     * @var string|null
     */
    public $bearerFormat;
}
