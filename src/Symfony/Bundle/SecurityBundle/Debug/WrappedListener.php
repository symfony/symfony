<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Debug;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Wraps a security listener for calls record.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class WrappedListener
{
    private $response;
    private $listener;
    private $time;
    private $stub;
    private static $hasVarDumper;

    public function __construct(callable $listener)
    {
        $this->listener = $listener;

        if (null === self::$hasVarDumper) {
            self::$hasVarDumper = class_exists(ClassStub::class);
        }
    }

    public function __invoke(RequestEvent $event)
    {
        $startTime = microtime(true);
        ($this->listener)($event);
        $this->time = microtime(true) - $startTime;
        $this->response = $event->getResponse();
    }

    /**
     * Proxies all method calls to the original listener.
     */
    public function __call($method, $arguments)
    {
        return $this->listener->{$method}(...$arguments);
    }

    public function getWrappedListener(): callable
    {
        return $this->listener;
    }

    public function getInfo(): array
    {
        if (null === $this->stub) {
            $this->stub = self::$hasVarDumper ? new ClassStub(\get_class($this->listener)) : \get_class($this->listener);
        }

        return [
            'response' => $this->response,
            'time' => $this->time,
            'stub' => $this->stub,
        ];
    }
}
