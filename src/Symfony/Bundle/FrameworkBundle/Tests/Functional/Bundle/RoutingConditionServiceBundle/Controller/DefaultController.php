<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\RoutingConditionServiceBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController
{
    #[Route(
        path: '/allowed/manually-tagged',
        condition: 'service("manually_tagged").giveMeTrue()',
    )]
    public function allowedByManuallyTagged(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/allowed/auto-configured',
        condition: 'service("auto_configured").flip(false)',
    )]
    public function allowedByAutoConfigured(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/allowed/auto-configured-non-aliased',
        condition: 'service("auto_configured_non_aliased").alwaysTrue()',
    )]
    public function allowedByAutoConfiguredNonAliased(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/denied/manually-tagged',
        condition: 'service("manually_tagged").giveMeFalse()',
    )]
    public function deniedByManuallyTagged(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/denied/auto-configured',
        condition: 'service("auto_configured").flip(true)',
    )]
    public function deniedByAutoConfigured(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/denied/auto-configured-non-aliased',
        condition: 'service("auto_configured_non_aliased").alwaysFalse()',
    )]
    public function deniedByAutoConfiguredNonAliased(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/denied/overridden',
        condition: 'service("foo").isAllowed()',
    )]
    public function deniedByOverriddenAlias(): Response
    {
        return new Response();
    }
}
