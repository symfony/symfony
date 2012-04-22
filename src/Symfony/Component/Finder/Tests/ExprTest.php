<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\Expr;

class ExprTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getGlobToRegexData
     */
    public function testGlobToRegex($glob, $match, $noMatch)
    {
        foreach ($match as $m) {
            $this->assertRegExp(Expr::create($glob)->getRegex(), $m, '::globToRegex() converts a glob to a regexp');
        }

        foreach ($noMatch as $m) {
            $this->assertNotRegExp(Expr::create($glob)->getRegex(), $m, '::globToRegex() converts a glob to a regexp');
        }
    }

    /**
     * @dataProvider getTypeGuesserData
     */
    public function testTypeGuesser($expr, $type)
    {
        $this->assertEquals($type, Expr::create($expr)->getType());
    }

    /**
     * @dataProvider getCaseSensitiveData
     */
    public function testCaseSensitive($expr, $isCaseSensitive)
    {
        $this->assertEquals($isCaseSensitive, Expr::create($expr)->isCaseSensitive());
    }

    /**
     * @dataProvider getRegexBodyData
     */
    public function testRegexBody($expr, $body)
    {
        $this->assertEquals($body, Expr::create($expr)->getBody());
    }

    public function getGlobToRegexData()
    {
        return array(
            array('', array(''), array('f', '/')),
            array('*', array('foo'), array('foo/', '/foo')),
            array('foo.*', array('foo.php', 'foo.a', 'foo.'), array('fooo.php', 'foo.php/foo')),
            array('fo?', array('foo', 'fot'), array('fooo', 'ffoo', 'fo/')),
            array('fo{o,t}', array('foo', 'fot'), array('fob', 'fo/')),
            array('foo(bar|foo)', array('foo(bar|foo)'), array('foobar', 'foofoo')),
            array('foo,bar', array('foo,bar'), array('foo', 'bar')),
            array('fo{o,\\,}', array('foo', 'fo,'), array()),
            array('fo{o,\\\\}', array('foo', 'fo\\'), array()),
            array('/foo', array('/foo'), array('foo')),
        );
    }

    public function getTypeGuesserData()
    {
        return array(
            array('{foo}', Expr::TYPE_REGEX),
            array('/foo/', Expr::TYPE_REGEX),
            array('foo',   Expr::TYPE_GLOB),
            array('foo*',  Expr::TYPE_GLOB),
        );
    }

    public function getCaseSensitiveData()
    {
        return array(
            array('{foo}m', true),
            array('/foo/i', false),
            array('foo*',   true),
        );
    }

    public function getRegexBodyData()
    {
        return array(
            array('{foo}m', 'foo'),
            array('/foo/i', 'foo'),
        );
    }
}
