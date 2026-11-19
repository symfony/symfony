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
 * Determines the JSON Schema a YAML document must validate against.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
interface SchemaResolverInterface
{
    /**
     * Turns the validation off for the document, even when another resolver would find a schema.
     */
    public const NONE = 'none';

    /**
     * As for editors, a location that starts with "file:", "http://" or "https://" is a URI, which can have a fragment.
     * Any other location is a path, even when it contains "#" or "://".
     *
     * @param string      $content The raw YAML content
     * @param string|null $file    The file path, or null when the content comes from STDIN
     *
     * @return string|null The schema location, self::NONE when the document must not be validated, or null when no schema is found
     */
    public function resolve(string $content, ?string $file = null): ?string;
}
