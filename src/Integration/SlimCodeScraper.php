<?php
namespace MuminovicAnel\OpenApiGenerator\Integration;

use App\Application\Actions\Action;
use Slim\App;
use MuminovicAnel\OpenApiGenerator\ReflectionsCollection;
use MuminovicAnel\OpenApiGenerator\Scraper\Endpoint;
use MuminovicAnel\OpenApiGenerator\Scraper\PathResultWrapper;
use MuminovicAnel\OpenApiGenerator\Scraper\Result;
use MuminovicAnel\OpenApiGenerator\Scraper\Server;
use MuminovicAnel\OpenApiGenerator\Scraper\Specification;
use MuminovicAnel\OpenApiGenerator\ScraperSkeleton;

abstract class SlimCodeScraper extends ScraperSkeleton
{
    protected string $actionClass = Action::class;

    /**
     * @param string $folder
     * @return App
     */
    public function getApp(string $folder): App
    {
        /** @var App $app */
        require_once $folder . '/app/app.php';
        return $app;
    }

    /**
     * @return PathResultWrapper|null
     */
    abstract public function getPathResultWrapper(): ?PathResultWrapper;

    public function getTitle()
    {
        return 'API Specification';
    }

    /**
     * @return array
     */
    public function scrape(string $folder): array
    {
        $app = $this->getApp($folder);
        $routes = $app->getRouteCollector()->getRoutes();

        $result = [];
        $result[0] = new Specification();
        $result[0]->version = static::$specificationVersion;
        $result[0]->title = static::$specificationTitle;
        $result[0]->description = sprintf(static::$specificationDescription, static::$specificationVersion);

        foreach ($this->getServers() as $serverUrl) {
            $result[0]->servers[] = new Server(['url' => $serverUrl]);
        }

        $path_wrapper = $this->getPathResultWrapper();

        foreach ($routes as $route) {
            $endpoint = new Endpoint();
            $pattern = $route->getPattern();
            $endpoint->id = $pattern;
            $endpoint->httpMethod = strtolower(current($route->getMethods()));

            $callable = $route->getCallable();
            if (is_string($callable) && class_exists($callable) && is_a($callable, $this->actionClass, true)) {
                $action_reflection = ReflectionsCollection::getMethod($callable, 'action');
                $action_doc = $action_reflection->getDocComment();
                $endpoint->callback = [$callable, 'action'];
            } else {
                $action_doc = null;
            }

            if (substr_count($pattern, '/') > 1) {
                $endpoint->tags[] = substr($pattern, 1, strpos($pattern, '/', 1) - 1);
            }

            // check for @auth tag
            if (
                isset($action_doc)
                && !empty($action_auth = $this->getDocParameter($action_doc, 'auth', ''))
            ) {
                if (isset($action_auth) && !empty($action_auth)) {
                    $this->ensureSecuritySchemeAdded($result[0], $action_auth);
                    $endpoint->securitySchemes[] = $action_auth;
                }
            }

            $endpoint->resultWrapper = $path_wrapper;
            $endpoint->result = 'null';

            $result[0]->endpoints[] = $endpoint;
        }

        return $result;
    }
}
