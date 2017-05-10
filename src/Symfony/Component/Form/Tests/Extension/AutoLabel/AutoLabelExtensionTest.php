<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\AutoLabel;

use Symfony\Component\Form\Extension\AutoLabel\AutoLabelExtension;

/**
 * @covers Symfony\Component\Form\Extension\AutoLabel\AutoLabelExtension
 */
class AutoLabelExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AutoLabelExtension
     */
    private $extension;

    public function setUp()
    {
        $this->extension = new AutoLabelExtension('foo');
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('form');

        $this->assertInternalType('array', $typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf('Symfony\Component\Form\Extension\AutoLabel\Type\AutoLabelTypeExtension', array_shift($typeExtensions));
    }
}
