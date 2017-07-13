<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests\Formatter;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Formatter\TextFormatter;

class TextFormatterTest extends \PHPUnit\Framework\TestCase
{
    private function getException($a1, $a2, $a3, $a4, $a5, $a6, $a7)
    {
        return FlattenException::create(new \Exception('foo'));
    }

    public function testTrace()
    {
        $formatter = new TextFormatter(true);

        $line = __LINE__ + 1;
        $exception = $this->getException(null, 1, 1.0, true, "foo\nbar", array(1, "b\t" => 2), new \stdClass());

        $content = $formatter->getContent($exception, true);

        $this->assertContains("\n  at Symfony\Component\Debug\Tests\Formatter\TextFormatterTest->getException(", $content);
        $this->assertContains("TextFormatterTest->getException(null, '1', '1', true, 'foo bar', array('1', 'b ' => '2'), object(stdClass))", $content);
        $this->assertContains('in '.__FILE__.':'.$line."\n", $content);

        $this->assertNotRegExp('@^\s*(?<!^|^  )[^\s]@m', $content, 'Lines are indented with 0 or 2 spaces');
        $this->assertNotRegExp('@ $@m', $content, 'Lines have no trailing white-space');
    }

    public function testNestedExceptions()
    {
        $formatter = new TextFormatter(true);

        $exception = FlattenException::create(new \RuntimeException('Foo', 0, new \RuntimeException('Bar')));
        $content = $formatter->getContent($exception, true);

        $this->assertRegExp('@\n2/2: RuntimeException\n  Foo\n.*:\d+\n\n\n1/2: RuntimeException\n  Bar\n@s', $content);
    }
}
