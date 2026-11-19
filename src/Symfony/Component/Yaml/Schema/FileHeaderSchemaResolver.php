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
 * A location that starts with "file:", "http://" or "https://" is kept as is.
 * Any other location is a path whose percent-encoded characters are decoded, so a space is written "%20".
 * A "#" after the first character starts a fragment, which ends at the next "#" and is not decoded.
 * A path with a fragment is returned as a "file://" URI, because only a URI can have a fragment.
 *
 * @see https://github.com/redhat-developer/yaml-language-server/blob/bf8ebb5d5065b7c91bf21be11ca1c68777a427ca/src/languageservice/services/yamlSchemaService.ts#L512-L534 How yaml-language-server resolves the same header
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

        if (str_starts_with($schema, 'file:') || str_starts_with($schema, 'http://') || str_starts_with($schema, 'https://')) {
            return $schema;
        }

        $fragment = '';
        if ($i = strpos($schema, '#')) {
            $fragment = explode('#', substr($schema, $i + 1), 2)[0];
            $schema = substr($schema, 0, $i);
        }

        $isAbsolute = self::isAbsolutePath($schema);
        $schema = rawurldecode($schema);

        if (null !== $file && !$isAbsolute) {
            $schema = \dirname($file).'/'.$schema;
        }

        if ('' === $fragment) {
            return $schema;
        }

        // In a path, a "#" decoded from "%23" is part of the file name.
        return self::toFileUri(self::isAbsolutePath($schema) ? $schema : getcwd().'/'.$schema).'#'.$fragment;
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

    /**
     * Same as path.isAbsolute() of Node.js on the current platform, which yaml-language-server calls before decoding the path.
     *
     * @see https://github.com/nodejs/node/blob/ab55d75c434fff79a3e5059a71e2b21edaf8554d/lib/path.js#L487-L500
     */
    private static function isAbsolutePath(string $path): bool
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return (bool) preg_match('{^(?:[A-Za-z]:)?[/\\\\]}', $path);
        }

        return str_starts_with($path, '/');
    }

    /**
     * Builds a "file://" URI from an absolute path, using forward slashes and percent-encoding the path segments.
     */
    private static function toFileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $drive = '';

        if ('\\' === \DIRECTORY_SEPARATOR && preg_match('{^[a-zA-Z]:/}', $path, $m)) {
            $drive = $m[0];
            $path = substr($path, 3);
        }

        $path = $drive.implode('/', array_map('rawurlencode', explode('/', $path)));

        // Windows paths ("C:/...") need an extra leading slash to form "file:///C:/...".
        return 'file://'.(str_starts_with($path, '/') ? '' : '/').$path;
    }
}
