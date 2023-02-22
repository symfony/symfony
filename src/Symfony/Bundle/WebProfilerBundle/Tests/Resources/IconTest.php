<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;

class IconTest extends TestCase
{
    /**
     * @dataProvider provideIconFilePaths
     */
    public function testIconFileContents($iconFilePath)
    {
        $iconFilePath = realpath($iconFilePath);
        $svgFileContents = file_get_contents($iconFilePath);

        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svgFileContents, sprintf('The SVG metadata of the "%s" icon must use "http://www.w3.org/2000/svg" as its "xmlns" value.', $iconFilePath));

        $this->assertMatchesRegularExpression('~<svg .* width="\d+".+>.*</svg>~s', file_get_contents($iconFilePath), sprintf('The SVG file of the "%s" icon must include a "width" attribute.', $iconFilePath));

        $this->assertMatchesRegularExpression('~<svg .* height="\d+".+>.*</svg>~s', file_get_contents($iconFilePath), sprintf('The SVG file of the "%s" icon must include a "height" attribute.', $iconFilePath));

        $this->assertMatchesRegularExpression('~<svg .* viewBox="0 0 \d+ \d+".+>.*</svg>~s', file_get_contents($iconFilePath), sprintf('The SVG file of the "%s" icon must include a "viewBox" attribute.', $iconFilePath));
    }

    public static function provideIconFilePaths()
    {
        return array_map(fn ($filePath) => (array) $filePath, glob(__DIR__.'/../../Resources/views/Icon/*.svg'));
    }
}
