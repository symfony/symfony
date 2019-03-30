<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp\Stream;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

class AbstractStreamTest extends TestCase
{
    /**
     * @dataProvider provideReplace
     */
    public function testReplace(string $expected, string $from, string $to, array $chunks)
    {
        $result = '';
        foreach (AbstractStream::replace($from, $to, $chunks) as $chunk) {
            $result .= $chunk;
        }

        $this->assertSame($expected, $result);
    }

    public function provideReplace()
    {
        yield ['ca', 'ab', 'c', ['a', 'b', 'a']];
        yield ['ac', 'ab', 'c', ['a', 'ab']];
        yield ['cbc', 'aba', 'c', ['ababa', 'ba']];
    }
}
