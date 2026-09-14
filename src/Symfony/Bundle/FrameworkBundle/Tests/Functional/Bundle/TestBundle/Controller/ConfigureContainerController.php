<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\ConfigureContainer\Greeter;
use Symfony\Component\HttpFoundation\Response;

class ConfigureContainerController
{
    public function __construct(
        private Greeter $greeter,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response(\sprintf('<html><body><p>%s</p><a href="/configure_container">Next</a></body></html>', $this->greeter->greet()));
    }
}
