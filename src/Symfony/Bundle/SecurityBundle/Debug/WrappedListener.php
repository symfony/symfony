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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Wraps a security listener for calls record.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class WrappedListener implements ListenerInterface
{
    private $response;
    private $listener;
    private $time;
    private $stub;
    private static $hasVarDumper;

    public function __construct(ListenerInterface $listener)
    {
        $this->listener = $listener;

        if (null === self::$hasVarDumper) {
            self::$hasVarDumper = class_exists(ClassStub::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(GetResponseEvent $event)
    {
        $startTime = microtime(true);
        $this->listener->handle($event);
        $this->time = microtime(true) - $startTime;
        $this->response = $event->getResponse();
    }

    /**
     * Proxies all method calls to the original listener.
     */
    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->listener, $method), $arguments);
    }

    public function getWrappedListener(): ListenerInterface
    {
        return $this->listener;
    }

    public function getInfo(): array
    {
        if (null === $this->stub) {
            $this->stub = self::$hasVarDumper ? new ClassStub(get_class($this->listener)) : get_class($this->listener);
        }

        return array(
            'response' => $this->response,
            'time' => $this->time,
            'stub' => $this->stub,
        );
    }
}
