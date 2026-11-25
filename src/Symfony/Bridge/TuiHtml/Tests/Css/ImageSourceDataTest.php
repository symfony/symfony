<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\HtmlRenderer\Css\StylesheetLoaderInterface;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;

#[RequiresPhpExtension('gd')]
final class ImageSourceDataTest extends TestCase
{
    public function testAuthorizedSourcesAreReadOnceIncludingMissingAddresses()
    {
        $loader = new class implements StylesheetLoaderInterface {
            public array $reads = [];
            public string $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="10"><rect width="20" height="10" fill="red"/></svg>';

            public function load(string $url): string
            {
                $this->reads[] = $url;

                return 'icon.svg' === $url ? $this->svg : '';
            }
        };
        $sheet = new CssStyleSheet($loader);
        $data = $sheet->getImageSourceData('icon.svg');
        self::assertSame($loader->svg, $data);
        self::assertSame(20.0, ImageResource::fromString($data)->width);
        $loader->svg = '';
        self::assertSame($data, $sheet->getImageSourceData('icon.svg'));
        self::assertNull($sheet->getImageSourceData('missing'));
        self::assertNull($sheet->getImageSourceData('missing'));
        self::assertSame(['icon.svg', 'missing'], $loader->reads);
        self::assertNull(new CssStyleSheet()->getImageSourceData('icon.svg'));
    }

    public function testRasterSourceUsesOriginalCachedBytes()
    {
        $data = file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png');
        $sheet = new CssStyleSheet();
        $uri = 'data:image/png;base64,'.base64_encode($data);
        self::assertSame($data, $sheet->getImageSourceData($uri));
        self::assertSame($data, $sheet->getImageSourceData($uri));
        self::assertSame([], new \ReflectionProperty($sheet, 'imageSources')->getValue($sheet));
    }

    public function testCombinedRasterAndRawSvgBudgetRejectsBeforeRetention()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="10"><rect width="20" height="10"/></svg>';
        $sheet = new CssStyleSheet();
        new \ReflectionProperty($sheet, 'imageBytes')->setValue($sheet, 64 * 1024 * 1024 - \strlen($svg));
        $uri = 'data:image/svg+xml,'.rawurlencode($svg);
        self::assertNull($sheet->getImageSourceData($uri));
        self::assertSame([], new \ReflectionProperty($sheet, 'imageSources')->getValue($sheet));
        self::assertNull(new CssStyleSheet()->getImageSourceData('data:image/png;base64,'.base64_encode(str_repeat('x', CssStyleSheet::MAX_IMAGE_BYTES + 1))));
    }
}
