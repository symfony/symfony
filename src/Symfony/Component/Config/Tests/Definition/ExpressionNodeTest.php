<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use Symfony\Component\Config\Definition\ExpressionNode;
use Symfony\Component\ExpressionLanguage\Expression;

class ExpressionNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getValidValues
     */
    public function testNormalize($input, $output)
    {
        $node = new ExpressionNode('test');
        $this->assertEquals($output, $node->normalize($input));
    }

    /**
     * @dataProvider getValidValues
     *
     * @param bool $value
     */
    public function testValidNonEmptyValues($input, $output)
    {
        $node = new ExpressionNode('test');
        $node->setAllowEmptyValue(false);

        if (null !== $output) {
            $this->assertEquals($output, $node->normalize($input));
        }
    }

    public function getValidValues()
    {
        return array(
            array(null, null),
            array('', null),
            array('foo', new Expression('foo')),
            array(new Expression('bar'), new Expression('bar')),
        );
    }
}
