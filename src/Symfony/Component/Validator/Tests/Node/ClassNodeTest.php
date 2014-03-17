<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Node;

use Symfony\Component\Validator\Node\ClassNode;

/**
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testConstructorExpectsObject()
    {
        $metadata = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataInterface');

        new ClassNode('foobar', null, $metadata, '', array(), array());
    }
}
