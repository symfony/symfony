<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use PHPUnit\Framework\TestCase;

class ClassAliasTest extends TestCase
{
    /**
     * Composer indexes declared classes only, so a file that just aliases one
     * is missing from an authoritative class map.
     */
    public function testAliasedClassesAreDeclared()
    {
        $root = \dirname(__DIR__);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $missing = [];

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ('php' !== $file->getExtension() || preg_match('{/(?:Tests|Resources)/}', $path)) {
                continue;
            }

            $code = file_get_contents($path);
            $name = $file->getBasename('.php');

            if (str_contains($code, 'class_alias(') && !preg_match('{\b(?:class|interface|trait|enum)\s+'.preg_quote($name, '{}').'\b}', $code)) {
                $missing[] = substr($path, 1 + \strlen($root));
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Composer cannot index a file that declares no class, so these aliases are unreachable with an authoritative class map. Declare the aliased class in an "if (false)" block.');
    }
}
