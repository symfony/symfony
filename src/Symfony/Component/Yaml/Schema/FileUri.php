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
 * Converts between local paths and "file:" URIs.
 *
 * @internal
 */
final class FileUri
{
    /**
     * Builds a "file://" URI from an absolute path, using forward slashes and percent-encoding the path segments.
     */
    public static function fromPath(string $path): string
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

    /**
     * Returns the decoded path of a local "file:" URI, without its query and fragment, or null when the URI is on another host.
     *
     * The path is the same as URI.parse($uri).fsPath of vscode-uri, which yaml-language-server uses, except that it is
     * never a UNC path, since that path would be read from another machine.
     *
     * @see https://github.com/microsoft/vscode-uri/blob/c22523764aa041c34841c42b815d280a694593b8/src/uri.ts#L564-L589
     */
    public static function toPath(string $uri): ?string
    {
        if (!preg_match('{^file:(?://([^/?#]*))?([^?#]*)}i', $uri, $m) || !\in_array(strtolower($m[1]), ['', 'localhost'], true)) {
            return null;
        }

        // A relative path is resolved against the root and leading slashes are merged: the path can neither hide a stream wrapper such as "phar://" nor be a UNC path.
        $path = '/'.ltrim(rawurldecode($m[2]), '/');

        // "file:///C:/schema.json" is the Windows path "c:/schema.json", on every platform.
        if (preg_match('{^/[a-zA-Z]:}', $path)) {
            $path = strtolower($path[1]).substr($path, 2);
        }

        return $path;
    }
}
