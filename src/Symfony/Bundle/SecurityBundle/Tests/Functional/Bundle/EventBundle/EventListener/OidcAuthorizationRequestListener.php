<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\EventListener;

use Symfony\Component\Security\Http\Event\OidcAuthorizationRequestEvent;

/**
 * Sets one parameter of the OIDC authorization request.
 */
final class OidcAuthorizationRequestListener
{
    public function __construct(
        private string $name,
        private string $value,
    ) {
    }

    public function __invoke(OidcAuthorizationRequestEvent $event): void
    {
        $event->setParam($this->name, $this->value);
    }
}
