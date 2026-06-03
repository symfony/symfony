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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Wraps a lazy security listener.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class WrappedLazyListener extends AbstractListener
{
    private ?Response $response = null;
    private FirewallListenerInterface $listener;
    private ?float $time = null;
    private ClassStub $stub;

    public function __construct(FirewallListenerInterface $listener)
    {
        $this->listener = $listener;
    }

    public function supports(Request $request): ?bool
    {
        return $this->listener->supports($request);
    }

    public function authenticate(RequestEvent $event): void
    {
        $startTime = microtime(true);

        try {
            $this->listener->authenticate($event);
        } catch (LazyResponseException $e) {
            $this->response = $e->getResponse();

            throw $e;
        } finally {
            $this->time = microtime(true) - $startTime;
        }

        $this->response = $event->getResponse();
    }

    public function getInfo(): array
    {
        return [
            'response' => $this->response,
            'time' => $this->time,
            'stub' => $this->stub ??= new ClassStub($this->listener instanceof TraceableAuthenticatorManagerListener ? $this->listener->getAuthenticatorManagerListener()::class : $this->listener::class),
        ];
    }

    /**
     * Proxies all method calls to the original listener.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->listener->{$method}(...$arguments);
    }
}
