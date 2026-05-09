<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Event;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

class SubmitEventTest extends TestCase
{
    #[DataProvider('isBlankProvider')]
    public function testIsBlank(string $value, bool $expected)
    {
        $event = new SubmitEvent($this->createStub(AbstractWidget::class), $value);

        $this->assertSame($expected, $event->isBlank());
    }

    public static function isBlankProvider(): iterable
    {
        yield 'empty string' => ['', true];
        yield 'whitespace only' => ['   ', true];
        yield 'tabs and spaces' => ["\t \n", true];
        yield 'non-empty value' => ['hello', false];
        yield 'value with surrounding whitespace' => ['  hello  ', false];
    }
}
