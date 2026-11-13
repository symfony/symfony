<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Figlet;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

class FontRegistryTest extends TestCase
{
    public function testGetBundledFont()
    {
        $registry = new FontRegistry();

        $font = $registry->get('big');

        $this->assertSame(8, $font->getHeight());
    }

    public function testRegisterCustomFont()
    {
        $registry = new FontRegistry();
        $fontsDir = \dirname(__DIR__, 3).'/Widget/Figlet/fonts';

        $registry->register('my-font', $fontsDir.'/small.flf');

        $this->assertTrue($registry->has('my-font'));
        $font = $registry->get('my-font');
        $this->assertSame(5, $font->getHeight()); // small font is 5 tall
    }

    public function testRegisterOverridesBundled()
    {
        $registry = new FontRegistry();
        $fontsDir = \dirname(__DIR__, 3).'/Widget/Figlet/fonts';

        // Override 'big' with 'small' font file
        $registry->register('big', $fontsDir.'/small.flf');

        $font = $registry->get('big');
        $this->assertSame(5, $font->getHeight()); // small font height, not big's 8
    }

    public function testReRegisterInvalidatesCache()
    {
        $registry = new FontRegistry();
        $fontsDir = \dirname(__DIR__, 3).'/Widget/Figlet/fonts';

        // Load big font (cached)
        $big = $registry->get('big');
        $this->assertSame(8, $big->getHeight());

        // Re-register with small font file
        $registry->register('big', $fontsDir.'/small.flf');

        // Should load the new file, not return cached
        $small = $registry->get('big');
        $this->assertSame(5, $small->getHeight());
    }

    public function testGetUnregisteredFontThrows()
    {
        $registry = new FontRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Font "unknown" is not registered');

        $registry->get('unknown');
    }

    public function testGetUnregisteredFontExceptionListsAvailable()
    {
        $registry = new FontRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"big", "small", "slant", "standard", "mini"');

        $registry->get('unknown');
    }

    public function testInvalidPathThrowsOnGet()
    {
        $registry = new FontRegistry();
        $registry->register('broken', '/nonexistent/path.flf');

        // Registration succeeds (lazy loading)
        $this->assertTrue($registry->has('broken'));

        // Loading fails
        $this->expectException(InvalidArgumentException::class);
        $registry->get('broken');
    }
}
