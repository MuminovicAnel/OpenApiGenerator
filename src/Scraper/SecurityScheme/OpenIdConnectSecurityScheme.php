<?php
namespace MuminovicAnel\OpenApiGenerator\Scraper\SecurityScheme;

class OpenIdConnectSecurityScheme extends \MuminovicAnel\OpenApiGenerator\InitableObject
{
    /**
     * @var string ID of security scheme
     */
    public $id;

    /**
     * @var string URL of the discovery endpoint
     */
    public $openIdConnectUrl;
}
