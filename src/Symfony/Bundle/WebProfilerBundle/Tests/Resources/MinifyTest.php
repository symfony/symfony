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
        $message = 'There cannot be any single line comment in this file. Consider using multiple line comment. ';
        $this->assertTrue(2 === substr_count(file_get_contents($dir.'/base_js.html.twig'), '//'), $message);
        $this->assertTrue(0 === substr_count(file_get_contents($dir.'/toolbar.css.twig'), '//'), $message);
    }
}
