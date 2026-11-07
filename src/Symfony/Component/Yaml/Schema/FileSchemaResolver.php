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

use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Uri;

/**
 * Resolves "file://" URIs to filesystem paths, decoding the percent-encoding
 * added by {@see SchemaValidator}.
 *
 * @internal
 */
final class FileSchemaResolver extends SchemaResolver
{
    protected function resolvePath(Uri $uri): ?string
    {
        if ('file' !== $uri->scheme()) {
            return parent::resolvePath($uri);
        }

        $path = rawurldecode($uri->path());

        // "file:///C:/..." refers to "C:/..." on Windows.
        if ('\\' === \DIRECTORY_SEPARATOR && preg_match('{^/[a-zA-Z]:/}', $path)) {
            $path = substr($path, 1);
        }

        return $path;
    }
}
