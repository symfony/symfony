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

use Symfony\Component\Serializer\Annotation\Expose;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExposeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new Expose(array('value' => 'Foobar'));
    }

    public function testExpose()
    {
        $expose = new Expose();
        $this->assertTrue($expose->getValue());
    }
}
