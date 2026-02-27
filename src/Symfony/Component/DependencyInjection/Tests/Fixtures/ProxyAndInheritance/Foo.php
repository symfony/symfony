<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\ProxyAndInheritance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Foo
{
    public function __construct(
        #[Autowire(lazy: true)]
        private Application $application,
    ) {
    }

    public function getApplicationName(): string
    {
        return $this->application->getName();
    }
}
