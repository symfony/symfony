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
            $isAlias = str_contains($code, 'class_alias(') || preg_match('{^class_exists\([^\n]+::class\);$}m', $code);

            if ($isAlias && !preg_match('{\b(?:class|interface|trait|enum)\s+'.preg_quote($name, '{}').'\b}', $code)) {
                $missing[] = substr($path, 1 + \strlen($root));
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Composer cannot index a file that declares no class, so these aliases are unreachable with an authoritative class map. Declare the aliased class in an "if (false)" block.');
    }

    public function testAliasedAttributesAreDeclaredAsAttributes()
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

            if (!str_contains($code, 'class_alias(') || !preg_match('{^use ([^ ;]++) as (\w++);$}m', $code, $m) || !class_exists($m[1])) {
                continue;
            }

            $target = new \ReflectionClass($m[1]);

            if (!$target->getAttributes(\Attribute::class)) {
                continue;
            }

            preg_match('{^#\[\\\\Attribute\b.*$}m', file_get_contents($target->getFileName()), $attribute);

            if (!$attribute || !preg_match('{^ *+'.preg_quote($attribute[0], '{}').'\n *+class '.$file->getBasename('.php').' extends '.$m[2].'\b}m', $code)) {
                $missing[] = substr($path, 1 + \strlen($root));
            }
        }

        sort($missing);

        $this->assertSame([], $missing, 'Static analyzers read the "if (false)" block of these aliases, so they report the alias as not being an attribute. Declare it with the same #[\Attribute] as the aliased class, and make it extend the aliased class.');
    }
}
