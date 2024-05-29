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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
trait TraceableListenerTrait
{
    private ?Response $response = null;
    private mixed $listener;
    private ?float $time = null;
    private object $stub;

    /**
     * Proxies all method calls to the original listener.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->listener->{$method}(...$arguments);
    }

    public function getWrappedListener(): mixed
    {
        return $this->listener;
    }

    public function getInfo(): array
    {
        return [
            'response' => $this->response,
            'time' => $this->time,
            'stub' => $this->stub ??= ClassStub::wrapCallable($this->listener instanceof TraceableAuthenticatorManagerListener ? $this->listener->getAuthenticatorManagerListener() : $this->listener),
        ];
    }
}
