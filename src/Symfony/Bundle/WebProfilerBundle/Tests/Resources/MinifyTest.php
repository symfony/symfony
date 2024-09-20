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

/**
 * Make sure we can minify content in toolbar.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MinifyTest extends TestCase
{
    public function testNoSingleLineComments()
    {
        $dir = \dirname(__DIR__, 2).'/Resources/views/Profiler';

        foreach (glob($dir.'/*js.html.twig') as $jsFile) {
            $fileContents = file_get_contents($dir.'/base_js.html.twig');
            $fileContents = str_replace('\'//\'', '', $fileContents);

            $this->assertEquals(0, substr_count($fileContents, '//'), 'There cannot be any single line comment in "'.$jsFile.'". Consider using multiple line comment. ');
        }
    }
}
