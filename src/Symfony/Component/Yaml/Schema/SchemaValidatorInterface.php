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

use Symfony\Component\Yaml\Exception\LogicException;
use Symfony\Component\Yaml\Exception\RuntimeException;

/**
 * Validates data, typically parsed from a YAML document, against a JSON Schema.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
interface SchemaValidatorInterface
{
    /**
     * Whether this validator can run, for example when the library it relies on is installed.
     */
    public function isSupported(): bool;

    /**
     * @param mixed       $data    The data to validate, typically the result of Yaml::parse()
     * @param string      $schema  The schema location, as returned by a SchemaResolverInterface
     * @param string|null $content The raw YAML content, used to locate the errors in the document
     *
     * @return list<array{message: string, line: int}> The validation errors, empty when the data is valid
     *
     * @throws LogicException   When this validator cannot run
     * @throws RuntimeException When the schema cannot be loaded or is not a valid JSON Schema
     */
    public function validate(mixed $data, string $schema, ?string $content = null): array;
}
