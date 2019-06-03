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
use Symfony\Component\Security\Http\Firewall\LegacyListenerTrait;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Wraps a security listener for calls record.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal since Symfony 4.3
 */
final class WrappedListener implements ListenerInterface
{
    use LegacyListenerTrait;

    private $response;
    private $listener;
    private $time;
    private $stub;
    private static $hasVarDumper;

    /**
     * @param callable $listener
     */
    public function __construct($listener)
    {
        $this->listener = $listener;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(RequestEvent $event)
    {
        $startTime = microtime(true);
        if (\is_callable($this->listener)) {
            ($this->listener)($event);
        } else {
            @trigger_error(sprintf('Calling the "%s::handle()" method from the firewall is deprecated since Symfony 4.3, implement "__invoke()" instead.', \get_class($this)), E_USER_DEPRECATED);
            $this->listener->handle($event);
        }
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

    public function getWrappedListener()
    {
        return $this->listener;
    }

    public function getInfo(): array
    {
        if (null !== $this->stub) {
            // no-op
        } elseif (self::$hasVarDumper ?? self::$hasVarDumper = class_exists(ClassStub::class)) {
            $this->stub = ClassStub::wrapCallable($this->listener);
        } elseif (\is_array($this->listener)) {
            $this->stub = (\is_object($this->listener[0]) ? \get_class($this->listener[0]) : $this->listener[0]).'::'.$this->listener[1];
        } elseif ($this->listener instanceof \Closure) {
            $r = new \ReflectionFunction($this->listener);
            if (false !== strpos($r->name, '{closure}')) {
                $this->stub = 'closure';
            } elseif ($class = $r->getClosureScopeClass()) {
                $this->stub = $class->name.'::'.$r->name;
            } else {
                $this->stub = $r->name;
            }
        } elseif (\is_string($this->listener)) {
            $this->stub = $this->listener;
        } else {
            $this->stub = \get_class($this->listener).'::__invoke';
        }

        return [
            'response' => $this->response,
            'time' => $this->time,
            'stub' => $this->stub,
        ];
    }
}
