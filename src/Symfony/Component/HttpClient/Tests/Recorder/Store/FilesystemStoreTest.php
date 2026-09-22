<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Recorder\Store;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Recorder\Store\FilesystemStore;

class FilesystemStoreTest extends TestCase
{
    public static function provideIsAbsolutePathCases(): array
    {
        return [
            ['', false],
            ['my.har', false],
            ['./my.har', false],
            ['/abs/my.har', true],
            ['\\\\server\\share\\my.har', true],
            ['C:\\records\\my.har', true],
            ['C:/records/my.har', true],
        ];
    }

    #[DataProvider('provideIsAbsolutePathCases')]
    public function testIsAbsolutePath(string $path, bool $expected)
    {
        $this->assertSame($expected, FilesystemStore::isAbsolutePath($path));
    }
}
