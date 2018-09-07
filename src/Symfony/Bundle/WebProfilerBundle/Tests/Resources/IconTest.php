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
        $this->assertRegExp('~<svg version="1\.1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="\d+" height="\d+" viewBox="0 0 \d+ \d+" enable-background="new 0 0 \d+ \d+" xml:space="preserve">.*</svg>~s', file_get_contents($iconFilePath), sprintf('The SVG metadata of the %s icon is different than expected (use the same as the other icons).', $iconFilePath));
    }

    public function provideIconFilePaths()
    {
        return array_map(function ($filePath) { return (array) $filePath; }, glob(__DIR__.'/../../Resources/views/Icon/*.svg'));
    }
}
