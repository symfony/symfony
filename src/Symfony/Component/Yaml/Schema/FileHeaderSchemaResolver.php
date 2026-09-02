<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Schema;

/**
 * Resolves the schema declared in the document header, before any YAML content.
 *
 * Both the "# yaml-language-server: $schema=" and the shorter "# $schema=" forms are
 * supported. A relative location is resolved against the directory of the file.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class FileHeaderSchemaResolver implements SchemaResolverInterface
{
    public function resolve(string $content, ?string $file = null): ?string
    {
        if (null === $schema = self::matchHeader($content)) {
            return null;
        }

        if (null !== $file && !str_contains($schema, '://') && !self::isAbsolutePath($schema)) {
            $schema = \dirname($file).'/'.$schema;
        }

        return $schema;
    }

    /**
     * Looks for a "$schema" declaration in the leading comment block, before any YAML content.
     */
    private static function matchHeader(string $content): ?string
    {
        foreach (preg_split('/\R/', $content) as $line) {
            $line = ltrim($line);

            if ('' === $line) {
                continue;
            }

            // Stop at the first line that is not a comment: the header only applies at the top of the file.
            if (!str_starts_with($line, '#')) {
                return null;
            }

            if (preg_match('/^#\s*(?:yaml-language-server:\s*)?\$schema=(\S+)/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function isAbsolutePath(string $path): bool
    {
        // Unix ("/...") and Windows ("C:/..." or "C:\...") absolute paths.
        return str_starts_with($path, '/') || 1 === preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }
}
