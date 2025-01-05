<?php

namespace EcliPhp\ApiGenerator\Class;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;


class ExtractRoutes
{
    private PhpDocParser $phpDocParser;
    private Lexer $lexer;

    public function __construct()
    {

        $config = new ParserConfig(usedAttributes: ['lines' => true, 'indexes' => true]);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    private function parseDoc(string $doc)
    {
        $tokens = new TokenIterator($this->lexer->tokenize($doc));
        return $this->phpDocParser->parse($tokens);
    }

    private function parseInjectedRule(string $rule): array
    {
        $rules = [];
        eval('$' . 'rules = ' . $rule . ';');
        return $rules;
    }

    private function parseComments(string $doc): array
    {
        if (!mb_strlen($doc))
            return [[], []];
        try {
            $phpDocNode = $this->parseDoc($doc);
            $responses = [];
            $rules = [];
            foreach ($phpDocNode->children ?? [] as $node) {

                $name = $node->name;
                $value = $node->value;

                switch ($name) {
                    case '@returnTS' :
                        $responses[] = $value;
                        break;
                    case '@returnPagination' :
                        $responses[] = "{ total?:number, per_page?:number, current_page?:number, last_page?:number, first_page_url?:string, last_page_url?:string, next_page_url?:string, prev_page_url?:string, path:string, from?:number, to?:number, data:($value)[] }";
                        break;
                    case '@returnCPagination' :
                        $responses[] = "{ per_page?:number, path:string, prev_cursor?:string, prev_page_url?:string, next_cursor?:string, next_page_url?:string, data:($value)[] }";
                        break;
                    case '@injectRules':
                        $rules = $this->parseInjectedRule($value);
                        break;
                }

            }

            return [
                $rules,
                $responses,
            ];
        } catch (\Exception $exception) {
            strpos($doc, 'inject') !== false && dd([
                $exception,
                $doc
            ]);
            return [[], []];
        }
    }

    private function cacheRoutes()
    {
        Artisan::command('route:cache', fn($any) => null);
    }

    private function parametersFromUri(string $uri)
    {
        $parameters = [];
        foreach (explode('/', $uri) as $p) {
            //echo "$p<br>";
            $strlen = mb_strlen($p);
            if (!$strlen)
                continue;
            if ('{' === $p[0] && $p[$strlen - 1] === '}') {
                $required = $p[$strlen - 2] !== '?';
                $parameters[substr($p, 1, $strlen - ($required ? 2 : 3))] = [
                    'required' => $required,
                    'type' => 'string',
                    'model' => null,
                ];
            }
        }
        return $parameters;
    }

    private function actionToMethod(string $action): \ReflectionMethod|null
    {
        $hasController = Str::startsWith($action, 'App\\Http\\Controllers');
        if (!$hasController)
            return null;
        $exploded = explode('@', $action);
        $cls = new \ReflectionClass($exploded[0]);
        return $cls->getMethod($exploded[1]);
    }

    private function extractRequestRules(\ReflectionMethod $method): array|null
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->hasType() && Str::startsWith($parameter->getType()->getName(), "App\\Http\\Requests")) {
                $cls = new \ReflectionClass($parameter->getType()->getName());
                $rules = $cls->newInstance();
                return array_map(
                    fn($el) => is_array($el) ?
                        array_filter($el, fn($el2) => is_string($el2)) : # @implement: objects and classes
                        explode('|', $el),
                    $rules->rules() ?? []);
            }
        }
        return null;
    }

    private function extractResponse(\ReflectionMethod $method)
    {

        $responses = [];
        if ($method->getReturnType()) {
            if (method_exists($method->getReturnType(), 'getTypes'))
                $responses = array_merge($responses, array_map(fn($el) => $el->getName(), $method->getReturnType()->getTypes()));
            else
                $responses[] = $method->getReturnType()->getName();
        }

        return $responses;
    }

    public function all()
    {
        $all = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $parameters = $this->parametersFromUri($route->uri);
            $reflectedMethod = $this->actionToMethod($route->action['uses']);
            $request = $reflectedMethod ? $this->extractRequestRules($reflectedMethod) : [];
            $response = $reflectedMethod ? $this->extractResponse($reflectedMethod) : [];
            [$requestFromComment, $responseFromComment] = $this->parseComments($reflectedMethod?->getDocComment() ? $reflectedMethod->getDocComment() : '');
            $all[] = [
                'controller' => $reflectedMethod?->class,
                'method' => $reflectedMethod?->getShortName(),
                'httpMethod' => array_filter(
                    array_map(fn($el) => strtoupper($el), $route->methods()),
                    fn($el) => $el !== 'HEAD'
                )[0],
                'uri' => $route->uri,
                'middlewares' => $route->gatherMiddleware(),
                'parameters' => $parameters,
                'request' => array_merge_recursive($request ?? [], $requestFromComment),
                'response' => array_merge_recursive($response ?? [], $responseFromComment),
            ];
        }
        return $all;
    }

}

