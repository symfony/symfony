<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Runtime\Runner\Psr\LaminasEmitter;

/**
 * A runtime that supports PSR-7, PSR-15 and PSR-17.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
class PsrRuntime extends GenericRuntime
{
    /**
     * @var ServerRequestCreator|null
     */
    private $requestCreator;

    private $options = [];

    /**
     * @param array {
     *   debug?: ?bool,
     *   server_request_creator?: ?string,
     *   psr17_server_request_factory?: ?string,
     *   psr17_uri_factory?: ?string,
     *   psr17_uploaded_file_factory?: ?string,
     *   psr17_stream_factory?: ?string,
     *   laminas_emitter?: ?string,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        parent::__construct($options);
    }

    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof RequestHandlerInterface) {
            return LaminasEmitter::createForRequestHandler($application, $this->createRequest(), ['emitter' => $this->options['laminas_emitter'] ?? null]);
        }

        if ($application instanceof ResponseInterface) {
            return LaminasEmitter::createForResponse($application, ['emitter' => $this->options['laminas_emitter'] ?? null]);
        }

        return parent::getRunner($application);
    }

    /**
     * @return mixed
     */
    protected function getArgument(\ReflectionParameter $parameter, ?string $type)
    {
        if (ServerRequestInterface::class === $type) {
            return $this->createRequest();
        }

        return parent::getArgument($parameter, $type);
    }

    /**
     * @return ServerRequestInterface
     */
    private function createRequest()
    {
        if (null === $this->requestCreator) {
            if (!class_exists(ServerRequestCreator::class)) {
                throw new \LogicException(sprintf('The "%s" class requires "nyholm/psr7-server". Try running "composer require nyholm/psr7-server".', self::class));
            }

            $creatorClass = $this->options['server_request_creator'] ?? ServerRequestCreator::class;
            if (isset($this->options['psr17_server_request_factory'], $this->options['psr17_uri_factory'], $this->options['psr17_uploaded_file_factory'], $this->options['psr17_stream_factory'])) {
                $this->requestCreator = new $creatorClass(
                    new $this->options['psr17_server_request_factory'](),
                    new $this->options['psr17_uri_factory'](),
                    new $this->options['psr17_uploaded_file_factory'](),
                    new $this->options['psr17_stream_factory']()
                );
            } elseif (class_exists(Psr17Factory::class)) {
                $psr17Factory = new Psr17Factory();
                $this->requestCreator = new $creatorClass($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
            } else {
                throw new \LogicException(sprintf('The "%s" class requires PSR-17 factories. Try running "composer require nyholm/psr7" or provide class names to the "%s"::__construct().', self::class, self::class));
            }
        }

        return $this->requestCreator->fromGlobals();
    }
}
