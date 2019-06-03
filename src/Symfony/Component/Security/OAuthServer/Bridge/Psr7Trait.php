<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Bridge;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
trait Psr7Trait
{
    /**
     * Create an internal request using Psr7 ServerRequestInterface.
     *
     * @param ServerRequestInterface $request
     */
    protected function createFromPsr7Request(ServerRequestInterface $request): void
    {
        $this->options['type'] = 'psr-7';
        $this->options['GET'] = $request->getQueryParams();
        $this->options['POST'] = $request->getParsedBody();
        $this->options['SERVER'] = $request->getServerParams();
    }

    protected function createPsr7Response(): ResponseInterface
    {
    }
}
