<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\KernelExtension;

class KernelExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $extension = new KernelExtension('foo', true);
        $this->assertTrue($extension->isDebug());
        $this->assertEquals('foo', $extension->getEnvironment());
    }
}
