<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\CssSelector;

use Symfony\Components\CssSelector\XPathExpr;

class XPathExprTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getXPathLiteralValues
     */
    public function testXpathLiteral($value, $literal)
    {
        $this->assertEquals($literal, XPathExpr::xpathLiteral($value));
    }

    public function getXPathLiteralValues()
    {
        return array(
            array('foo', "'foo'"),
            array("foo's bar", '"foo\'s bar"'),
            array("foo's \"middle\" bar", 'concat(\'foo\', "\'", \'s "middle" bar\')'),
            array("foo's 'middle' \"bar\"", 'concat(\'foo\', "\'", \'s \', "\'", \'middle\', "\'", \' "bar"\')'),
        );
    }
}
