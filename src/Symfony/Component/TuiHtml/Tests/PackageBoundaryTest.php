<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests;

use PHPUnit\Framework\TestCase;

final class PackageBoundaryTest extends TestCase
{
    public function testCoreDoesNotDependOnHtmlRendering()
    {
        $core = str_replace('\\', '/', \dirname(__DIR__, 2)).'/Tui';
        $violations = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($core)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (!$file->isFile() || 'php' !== $file->getExtension() || str_contains($path, '/vendor/') || str_contains($path, '/Tests/')) {
                continue;
            }
            if (preg_match('/\\\\Dom\\\\|Symfony\\\\Component\\\\TuiHtml\\\\/', file_get_contents($path))) {
                $violations[] = substr($path, \strlen($core) + 1);
            }
        }
        sort($violations);
        $this->assertSame([], $violations);
    }
}
