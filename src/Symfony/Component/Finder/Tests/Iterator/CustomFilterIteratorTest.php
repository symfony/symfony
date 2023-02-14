<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Iterator\CustomFilterIterator;

class CustomFilterIteratorTest extends IteratorTestCase
{
    public function testWithInvalidFilter()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomFilterIterator(new Iterator(), ['foo']);
    }

    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($filters, $expected)
    {
        $inner = new Iterator(['test.php', 'test.py', 'foo.php']);

        $iterator = new CustomFilterIterator($inner, $filters);

        $this->assertIterator($expected, $iterator);
    }

    public static function getAcceptData()
    {
        return [
            [[fn (\SplFileInfo $fileinfo) => false], []],
            [[fn (\SplFileInfo $fileinfo) => str_starts_with($fileinfo, 'test')], ['test.php', 'test.py']],
            [['is_dir'], []],
        ];
    }
}
