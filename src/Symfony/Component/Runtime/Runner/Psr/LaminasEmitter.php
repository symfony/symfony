<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner\Psr;

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
class LaminasEmitter implements RunnerInterface
{
    private $requestHandler;
    private $response;
    private $request;
    private $emitter;

    private function __construct(array $options)
    {
        $class = $options['emitter'] ?? SapiEmitter::class;
        if (!class_exists(SapiEmitter::class)) {
            throw new \LogicException(sprintf('The "%s" class requires "laminas/laminas-httphandlerrunner". Try running "composer require laminas/laminas-httphandlerrunner".', self::class));
        }

        if (!class_exists($class)) {
            throw new \LogicException(sprintf('The class "%s" cannot be found.', $class));
        }

        $this->emitter = new $class();
    }

    /**
     * @param array{emitter?: ?string} $options
     */
    public static function createForResponse(ResponseInterface $response, array $options): self
    {
        $emitter = new self($options);
        $emitter->response = $response;

        return $emitter;
    }

    /**
     * @param array{emitter?: ?string} $options
     */
    public static function createForRequestHandler(RequestHandlerInterface $handler, ServerRequestInterface $request, array $options): self
    {
        $emitter = new self($options);
        $emitter->requestHandler = $handler;
        $emitter->request = $request;

        return $emitter;
    }

    public function run(): int
    {
        if (null === $this->response) {
            $this->response = $this->requestHandler->handle($this->request);
        }

        $this->emitter->emit($this->response);

        return 0;
    }
}
