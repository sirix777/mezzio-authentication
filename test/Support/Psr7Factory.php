<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Support;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final readonly class Psr7Factory
{
    private ResponseFactory $responseFactory;
    private ServerRequestFactory $serverRequestFactory;

    public function __construct()
    {
        $this->responseFactory = new ResponseFactory();
        $this->serverRequestFactory = new ServerRequestFactory();
    }

    public function createResponse(int $code = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($code);
    }

    public function createServerRequest(string $method, string $uri): ServerRequestInterface
    {
        return $this->serverRequestFactory->createServerRequest($method, $uri);
    }
}
