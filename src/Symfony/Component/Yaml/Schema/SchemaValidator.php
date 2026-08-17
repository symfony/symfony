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

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Exception\LogicException;
use Symfony\Component\Yaml\Exception\RuntimeException;

/**
 * Validates data, typically parsed from a YAML document, against a JSON Schema.
 *
 * Relies on the "opis/json-schema" package.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class SchemaValidator implements SchemaValidatorInterface
{
    private ?Validator $validator = null;

    /**
     * @var array<string, string> Filesystem paths already registered on the validator, keyed by schema id
     */
    private array $registeredFiles = [];

    public function isSupported(): bool
    {
        return class_exists(Validator::class);
    }

    /**
     * @throws RuntimeException When the schema is remote, since only local schema files are supported
     */
    public function validate(mixed $data, string $schema, ?string $content = null): array
    {
        if (!$this->isSupported()) {
            throw new LogicException('Validating against a JSON Schema requires the "opis/json-schema" package. Try running "composer require opis/json-schema".');
        }

        if (str_contains($schema, '://') && !str_starts_with($schema, 'file://')) {
            throw new RuntimeException(\sprintf('Cannot validate against remote schema "%s": only local schema files are supported.', $schema));
        }

        try {
            $data = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // A silent failure would validate null instead, and report the file as valid.
            throw new RuntimeException(\sprintf('Unable to convert the content to JSON before validating it against schema "%s": ', $schema).$e->getMessage(), 0, $e);
        }

        // Disable default value application: a linter validates the actual content and must
        // not inject (then check) schema defaults, which may be null on non-nullable nodes.
        // Keep validating after the first violation, so one run reports them all.
        $validator = $this->validator ??= new Validator(new SchemaLoader(new SchemaParser([], ['allowDefaults' => false]), new SchemaResolver(), true), 100, false);

        if (is_file($schema)) {
            // Register the file so relative and internal "$ref" resolve against its location.
            $path = realpath($schema) ?: $schema;
            $id = self::toFileUri($path);

            if (!isset($this->registeredFiles[$id])) {
                $validator->resolver()->registerFile($id, $path);
                $this->registeredFiles[$id] = $path;
            }

            $schema = $id;
        }

        try {
            $result = $validator->validate($data, $schema);
        } catch (\Exception $e) {
            // A missing file, an unreachable URL or an invalid schema aborts a single file, not the whole run.
            throw new RuntimeException(\sprintf('Unable to validate against schema "%s": ', $schema).$e->getMessage(), 0, $e);
        }

        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter())->formatKeyed($result->error()) as $pointer => $messages) {
            // The formatter percent-encodes pointer segments (for example "@" as "%40").
            $pointer = rawurldecode($pointer);
            $property = trim($pointer, '/');

            foreach ($messages as $message) {
                $errors[] = [
                    'message' => ($property ? str_replace('/', '.', $property).': ' : '').$message,
                    'line' => null === $content ? 0 : self::locateLine($content, $pointer),
                ];
            }
        }

        return $errors;
    }

    /**
     * Builds a "file://" URI from a filesystem path, using forward slashes so Windows paths stay valid URIs.
     */
    private static function toFileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        // Windows paths ("C:/...") need an extra leading slash to form "file:///C:/...".
        return 'file://'.(str_starts_with($path, '/') ? '' : '/').$path;
    }

    /**
     * Best-effort resolution of the YAML line for a JSON Schema error path.
     *
     * The path is a JSON Pointer (for example "/when@prod/framework") or a dotted
     * property path. The line of the deepest matching key is returned, or 0 when
     * it cannot be located.
     */
    private static function locateLine(string $content, string $path): int
    {
        $path = str_replace(['~1', '~0'], ['/', '~'], $path);
        $segments = preg_split('#[/.]#', trim($path, '/.'), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        $lines = explode("\n", $content);
        $line = 0;
        $offset = 0;

        foreach ($segments as $segment) {
            // Array indices in the path do not map to a key in the document.
            if (ctype_digit($segment)) {
                continue;
            }

            for ($i = $offset; $i < \count($lines); ++$i) {
                if (preg_match('/^\s*'.preg_quote($segment, '/').'\s*:/', $lines[$i])) {
                    $line = $i + 1;
                    $offset = $i + 1;
                    break;
                }
            }
        }

        return $line;
    }
}
