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
 * The header follows the syntax of yaml-language-server: "# yaml-language-server: $schema=<location>" or "# $schema: <location>".
 * The "none" location, in any letter case, resolves to self::NONE so that the document is not validated.
 * A relative location is resolved against the directory of the file.
 *
 * A location that starts with "http://" or "https://" is kept as is.
 * A local "file:" URI is turned into its decoded path, as yaml-language-server does, unless it has a fragment.
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

        if (0 === strcasecmp($schema, self::NONE)) {
            return self::NONE;
        }

        if (str_starts_with($schema, 'http://') || str_starts_with($schema, 'https://')) {
            return $schema;
        }

        if (str_starts_with($schema, 'file:')) {
            // Only a URI can have a fragment.
            if ('' !== (explode('#', $schema, 2)[1] ?? '') || null === $path = FileUri::toPath($schema)) {
                return $schema;
            }

            return $path;
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
        return FileUri::fromPath(self::isAbsolutePath($schema) ? $schema : getcwd().'/'.$schema).'#'.$fragment;
    }

    /**
     * Finds the modeline the way yaml-language-server does, in the comments before the first YAML node.
     */
    private static function matchHeader(string $content): ?string
    {
        if (str_starts_with($content, "\u{FEFF}")) {
            $content = substr($content, 3);
        }

        $inDocument = false;

        foreach (preg_split('/\R/', $content) as $line) {
            if (!$inDocument) {
                // Directives, such as "%YAML 1.2", can only come before the document start marker.
                if (str_starts_with($line, '%')) {
                    continue;
                }

                if (preg_match('/^---(?=[ \t]|$)/', $line)) {
                    $inDocument = true;
                    $line = substr($line, 3);
                }
            }

            $line = ltrim($line, " \t");

            if ('' === $line) {
                continue;
            }

            // Stop at the first line that is not a comment: the header only applies at the top of the file.
            if (!str_starts_with($line, '#')) {
                return null;
            }

            if (preg_match('/^#\s+(?:yaml-language-server\s*:|\$schema:)/', $line)) {
                return preg_match('/\$schema(?:=|:[^\S\r\n]*)(\S+)/', $line, $matches) ? $matches[1] : null;
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
}
