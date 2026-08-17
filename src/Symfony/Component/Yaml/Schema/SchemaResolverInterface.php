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
     * @param string      $content The raw YAML content
     * @param string|null $file    The file path, or null when the content comes from STDIN
     *
     * @return string|null The schema location, or null when no schema applies
     */
    public function resolve(string $content, ?string $file = null): ?string;
}
