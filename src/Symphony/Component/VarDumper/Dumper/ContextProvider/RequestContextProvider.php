<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Dumper\ContextProvider;

use Symphony\Component\HttpFoundation\RequestStack;

/**
 * Tries to provide context from a request.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class RequestContextProvider implements ContextProviderInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getContext(): ?array
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return null;
        }

        return array(
            'uri' => $request->getUri(),
            'method' => $request->getMethod(),
            'controller' => $request->attributes->get('_controller'),
            'identifier' => spl_object_hash($request),
        );
    }
}
