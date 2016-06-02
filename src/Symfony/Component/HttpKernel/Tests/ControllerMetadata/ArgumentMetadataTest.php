<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\ControllerMetadata;

use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ArgumentMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultValueAvailable()
    {
        $argument = new ArgumentMetadata('foo', 'string', false, true, 'default value');

        $this->assertTrue($argument->hasDefaultValue());
        $this->assertSame('default value', $argument->getDefaultValue());
    }

    /**
     * @expectedException \LogicException
     */
    public function testDefaultValueUnavailable()
    {
        $argument = new ArgumentMetadata('foo', 'string', false, false, null);

        $this->assertFalse($argument->hasDefaultValue());
        $argument->getDefaultValue();
    }
}
