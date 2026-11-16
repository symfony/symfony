<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Css;

use Symfony\Component\TuiHtml\Exception\InvalidArgumentException;

/**
 * Loads stylesheets from a list of directories, the first match wins.
 *
 * An address is a path in them, whatever its scheme, like file:///etc/x.css for etc/x.css: it cannot reach a parent
 * directory, but a symbolic link put in them is followed, like the bundle assets installed as links.
 *
 * @experimental
 */
final class FilesystemStylesheetLoader implements StylesheetLoaderInterface
{
    /**
     * @param list<string> $directories
     */
    public function __construct(private readonly array $directories)
    {
    }

    public function load(string $href): string
    {
        $path = ltrim(parse_url($href, \PHP_URL_PATH) ?: $href, '/');
        // A backslash separates directories on Windows, like in ..\secret.css
        if (str_contains('/'.$path.'/', '/../') || str_contains($path, '\\') || str_contains($path, "\0")) {
            throw new InvalidArgumentException(\sprintf('The stylesheet "%s" cannot reference a parent directory.', $href));
        }
        foreach ($this->directories as $directory) {
            // Only a local file under the directories of the application can be read, the path cannot leave them
            /**
             * @psalm-taint-escape file
             * @psalm-taint-escape ssrf
             * @psalm-taint-escape url_component
             * @psalm-taint-escape url_path
             */
            $file = rtrim($directory, '/').'/'.$path;
            if (is_file($file) && false !== $css = file_get_contents($file)) {
                return $css;
            }
        }
        // Not where it was looked for: the message may be shown or logged, the directories are those of the application
        throw new InvalidArgumentException(\sprintf('The stylesheet "%s" was not found.', $href));
    }
}
