<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use Symfony\Component\Serializer\Annotation\ReadOnly;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ReadOnlyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new ReadOnly(array('value' => 'Foobar'));
    }

    public function testReadOnly()
    {
        $readOnly = new ReadOnly();
        $this->assertTrue($readOnly->getReadOnly());

        $readOnly = new ReadOnly(array('value' => true));
        $this->assertTrue($readOnly->getReadOnly());

        $readOnly = new ReadOnly(array('value' => false));
        $this->assertFalse($readOnly->getReadOnly());
    }
}
