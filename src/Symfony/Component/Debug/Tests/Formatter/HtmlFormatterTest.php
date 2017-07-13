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
use Symfony\Component\Debug\Formatter\HtmlFormatter;

class HtmlFormatterTest extends \PHPUnit\Framework\TestCase
{
    private function getException($a1, $a2, $a3, $a4, $a5, $a6, $a7)
    {
        return FlattenException::create(new \Exception('foo'));
    }

    public function testTrace()
    {
        $formatter = new HtmlFormatter();

        $line = __LINE__ + 1;
        $exception = $this->getException(null, 1, 1.0, true, 'foo"<bar', array(1, 'b<' => 2), new \stdClass());

        $content = $formatter->getContent($exception, true);

        $this->assertContains('<td>at <span class="trace-class"><abbr title="Symfony\Component\Debug\Tests\Formatter\HtmlFormatterTest">HtmlFormatterTest</abbr></span><span class="trace-type">-></span><span class="trace-method">getException</span>', $content);
        $this->assertContains("<em>null</em>, 1, 1.0, <em>true</em>, 'foo&quot;&lt;bar', <em>array</em>(1, 'b&lt;' => 2), <em>object</em>(<abbr title=\"stdClass\">stdClass</abbr>)", $content);
        $this->assertRegExp('@in <a[^>]+><strong>HtmlFormatterTest.php</strong> \(line '.$line.'\)</a>@', $content);

        $content = $formatter->getContent($exception, false);

        $this->assertNotContains('HtmlFormatterTest', $content);
    }

    public function testNestedExceptions()
    {
        $formatter = new HtmlFormatter();

        $exception = FlattenException::create(new \RuntimeException('Foo', 0, new \RuntimeException('Bar')));
        $content = $formatter->getContent($exception, true);

        $this->assertStringMatchesFormat('%A<p class="break-long-words trace-message">Foo</p>%A<p class="break-long-words trace-message">Bar</p>%A', $content);
    }
}
