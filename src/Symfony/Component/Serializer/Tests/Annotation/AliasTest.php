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

use Symfony\Component\Serializer\Annotation\Alias;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AliasTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testEmptyParameter()
    {
        new Alias(array('value' => ''));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testNotAStringGroupsParameter()
    {
        new Alias(array('value' => array()));
    }

    public function testGroupsParameters()
    {
        $validData = 'a';

        $alias = new Alias(array('value' => $validData));
        $this->assertEquals($validData, $alias->getName());
    }
}
