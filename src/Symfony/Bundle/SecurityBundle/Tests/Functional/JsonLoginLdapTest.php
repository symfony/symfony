<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpKernel\Kernel;

class JsonLoginLdapTest extends AbstractWebTestCase
{
    public function testKernelBoot()
    {
        $kernel = self::createKernel(['test_case' => 'JsonLoginLdap', 'root_config' => 'config.yml']);
        $kernel->boot();

        $this->assertInstanceOf(Kernel::class, $kernel);
    }
}
