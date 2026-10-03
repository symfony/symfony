<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Something behind a firewall to request, so that the firewall of its pattern actually runs.
 *
 * Routing happens before the firewall listener, so a path no route matches never reaches it.
 */
final class ProtectedController
{
    public function __invoke(): Response
    {
        return new Response('protected');
    }
}
