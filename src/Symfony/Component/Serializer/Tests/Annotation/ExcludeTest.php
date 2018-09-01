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

use Symfony\Component\Serializer\Annotation\Exclude;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExcludeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new Exclude(array('value' => 'Foobar'));
    }

    public function testExclude()
    {
        $exclude = new Exclude();
        $this->assertTrue($exclude->getValue());
    }
}
