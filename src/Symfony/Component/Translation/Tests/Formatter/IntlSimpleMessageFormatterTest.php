<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\IntlSimpleMessageFormatter;

class IntlSimpleMessageFormatterTest extends TestCase
{
    /**
     * @dataProvider getTestStrings
     */
    public function testFormat(string $input, string $expected, array $params = array(), string $locale = 'en')
    {
        $formatter = new IntlSimpleMessageFormatter();
        $result = $formatter->format($input, 'en', $params, $locale);
        $this->assertEquals($expected, $result);
    }

    public function getTestStrings()
    {
        $apples = '{ COUNT, plural,
        =0 {{name}, there are no apples}
        =1 {{name}, there is one apple}
        other {{name}, there are # apples}
        }';

        yield array('foobar', 'foobar');
        yield array('foo {name} bar', 'foo test bar', array('name' => 'test'));
        yield array($apples, 'Foo, there are no apples', array('COUNT' => 0, 'name' => 'Foo'));
        yield array($apples, 'Foo, there is one apple', array('COUNT' => 1, 'name' => 'Foo'));
        yield array($apples, 'Foo, there are 2 apples', array('COUNT' => 2, 'name' => 'Foo'));
        yield array('Hello {name}. There are {COUNT, plural, zero{no apples} other{some apples}} in the basket', 'Hello Foo. There are no apples in the basket', array('COUNT' => 0, 'name' => 'Foo'));
        yield array('Hello {name}. There are {COUNT, plural, zero{no apples} other{some apples}} in the basket', 'Hello Foo. There are some apples in the basket', array('COUNT' => 3, 'name' => 'Foo'));

        // Test select
        $gender = 'I think { GENDER, select,
       male {he}
       female {she}
       other {they}
   } liked this.';
        yield array($gender, 'I think he liked this.', array('GENDER' => 'male'));
        yield array($gender, 'I think she liked this.', array('GENDER' => 'female'));
        yield array($gender, 'I think they liked this.', array());
    }
}
