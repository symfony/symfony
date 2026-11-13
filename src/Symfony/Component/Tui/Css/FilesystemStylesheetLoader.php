<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Loads stylesheets from a list of directories, the first match wins.
 *
 * @experimental
 */
final class FilesystemStylesheetLoader implements StylesheetLoaderInterface
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        private readonly array $directories,
    ) {
    }

    public function load(string $href): string
    {
        $path = ltrim(parse_url($href, \PHP_URL_PATH) ?: $href, '/');
        if (str_contains('/'.$path.'/', '/../')) {
            throw new InvalidArgumentException(\sprintf('The stylesheet "%s" cannot reference a parent directory.', $href));
        }

        foreach ($this->directories as $directory) {
            if (is_file($file = rtrim($directory, '/').'/'.$path) && false !== $css = file_get_contents($file)) {
                return $css;
            }
        }

        throw new InvalidArgumentException(\sprintf('The stylesheet "%s" was not found in "%s".', $href, implode('", "', $this->directories)));
    }
}
