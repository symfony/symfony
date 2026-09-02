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
     * The path is a JSON Pointer (for example "/when@prod/framework" or "/paths/0/name").
     * Keys are matched at the indentation level of the block they belong to, and numeric
     * segments follow block sequence items, so a key nested in another block is never
     * mistaken for the target. The line of the deepest located segment is returned, or 0
     * when none is located. A value in flow notation resolves to the line where it starts.
     */
    private static function locateLine(string $content, string $path): int
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ('' !== $segment) {
                $segments[] = str_replace(['~1', '~0'], ['/', '~'], $segment);
            }
        }

        $lines = explode("\n", $content);
        $line = 0;
        $start = 0;
        $end = \count($lines);
        $compactItem = false;

        foreach ($segments as $segment) {
            $found = false;

            // A digit can be a sequence index or a mapping key: peek at the block to know.
            if (ctype_digit($segment) && self::startsWithSequenceItem($lines, $start, $end)) {
                $remaining = (int) $segment;
                $itemIndent = null;

                for ($i = $start; $i < $end; ++$i) {
                    $text = rtrim(ltrim($lines[$i], ' '));
                    if ('' === $text || '#' === $text[0]) {
                        continue;
                    }
                    $indent = \strlen(rtrim($lines[$i])) - \strlen($text);
                    $isItem = '-' === $text || str_starts_with($text, '- ');

                    if (null === $itemIndent) {
                        $itemIndent = $indent;
                    } elseif ($indent > $itemIndent) {
                        continue;
                    } elseif ($indent < $itemIndent || !$isItem) {
                        break;
                    }

                    if ($remaining-- > 0) {
                        continue;
                    }

                    $line = $i + 1;
                    $start = $i;
                    $end = self::findBlockEnd($lines, $i + 1, $end, $itemIndent, true);
                    $compactItem = true;
                    $found = true;
                    break;
                }
            } else {
                $childIndent = null;
                $pattern = '/^(["\']?)'.preg_quote($segment, '/').'\1\s*:(.*)$/';

                for ($i = $start; $i < $end; ++$i) {
                    $text = rtrim(ltrim($lines[$i], ' '));
                    if ($compactItem && $i === $start && ('-' === $text || str_starts_with($text, '- '))) {
                        // The first key of a sequence item can sit on the "-" line itself.
                        $text = ltrim(substr($text, 1), ' ');
                    }
                    if ('' === $text || '#' === $text[0]) {
                        continue;
                    }
                    $indent = \strlen(rtrim($lines[$i])) - \strlen($text);
                    $childIndent ??= $indent;

                    if ($indent !== $childIndent || !preg_match($pattern, $text, $matches)) {
                        continue;
                    }

                    $line = $i + 1;
                    $rest = trim($matches[2]);
                    if ('' !== $rest && '#' !== $rest[0]) {
                        // The value is inline, a scalar or a flow collection: deeper
                        // segments cannot be resolved to a more precise line.
                        return $line;
                    }

                    $start = $i + 1;
                    $end = self::findBlockEnd($lines, $i + 1, $end, $childIndent, false);
                    $compactItem = false;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                break;
            }
        }

        return $line;
    }

    /**
     * Tells whether the first content line of the range is a block sequence item.
     */
    private static function startsWithSequenceItem(array $lines, int $start, int $end): bool
    {
        for ($i = $start; $i < $end; ++$i) {
            $text = rtrim(ltrim($lines[$i], ' '));
            if ('' !== $text && '#' !== $text[0]) {
                return '-' === $text || str_starts_with($text, '- ');
            }
        }

        return false;
    }

    /**
     * Returns the exclusive end of the block made of the lines more indented than $indent.
     *
     * Sequence items indented exactly at $indent are part of the block when $stopAtItems
     * is false, which matches the value of a mapping key whose items are not indented.
     */
    private static function findBlockEnd(array $lines, int $start, int $end, int $indent, bool $stopAtItems): int
    {
        for ($i = $start; $i < $end; ++$i) {
            $text = rtrim(ltrim($lines[$i], ' '));
            if ('' === $text || '#' === $text[0]) {
                continue;
            }
            $lineIndent = \strlen(rtrim($lines[$i])) - \strlen($text);
            if ($lineIndent < $indent || ($lineIndent === $indent && ($stopAtItems || ('-' !== $text && !str_starts_with($text, '- '))))) {
                return $i;
            }
        }

        return $end;
    }
}
