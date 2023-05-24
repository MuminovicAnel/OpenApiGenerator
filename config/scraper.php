<?php
namespace MuminovicAnel\OpenApiGenerator\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use MuminovicAnel\OpenApiGenerator\Extractor\LaravelFormRequestExtractor;
use MuminovicAnel\OpenApiGenerator\Scraper\Endpoint;
use MuminovicAnel\OpenApiGenerator\Scraper\Server;
use MuminovicAnel\OpenApiGenerator\Scraper\Specification;
use MuminovicAnel\OpenApiGenerator\ScraperSkeleton;

class CustomCodeScraper extends ScraperSkeleton
{

    public function scrape(string $folder): array
    {
        $prefix = 'v1';
        $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(function($route) use ($prefix) {
            $prefixRoute = strtolower($route->action['prefix']);
            return $prefixRoute == '/' . env('API_ENDPOINT') . '/' . $prefix || $prefixRoute == '/' . env('API_ENDPOINT') . '/system';
        })->all();

        $result = [];

        $result[0] = new Specification();
        $result[0]->version = env('API_VERSION');
        $result[0]->title = env('APP_NAME');
        $result[0]->description = env('APP_NAME') . ' version ' .  env('API_VERSION');

        $result[0]->servers[] = new Server(['url' => url(''), 'description' => 'Generated server url']);
        
        $path_wrapper = $this->getDefaultResponseWrapper();

        foreach ($routes as $route) {
            $endpoint = new Endpoint();
            $pattern = '/'.ltrim($route->uri(), '/');
            $endpoint->id = $pattern;
            $endpoint->httpMethod = strtolower(current($route->methods()));

            if (isset($route->action['controller'])) {
                $callable = $route->action['controller'];
                if (strpos($callable, '@') && (list($controller, $action) = explode('@', $callable))
                    && class_exists($controller) && is_a($controller, \Illuminate\Routing\Controller::class, true)) {
                    $endpoint->callback = [$controller, $action];
                }
            } else if (isset($route->action['uses']) && is_callable($route->action['uses'])) {
                $endpoint->callback = $route->action['uses'];
            }
            if (substr_count($pattern, '/') > 1) {
                $endpoint->tags[] = substr($pattern, 1, strpos($pattern, '/', 1) - 1);
            }
            $endpoint->resultWrapper = $path_wrapper;

            $result[0]->endpoints[] = $endpoint;
        }

        return $result;
    }

    public function getArgumentExtractors(): array
    {
        return [
            FormRequest::class => LaravelFormRequestExtractor::class,
        ];
    }

    public function getClassDescribingOptions(): array
    {
        return array_merge(parent::getClassDescribingOptions(), [
            \Symfony\Component\HttpFoundation\Response::class => [],
        ]);
    }
}
