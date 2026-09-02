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
     * The path is a JSON Pointer, for example "/when@prod/framework" or
     * "/monolog/handlers/0/type". Each segment is looked up among the direct
     * children of the previous one, so a key nested deeper in the document is
     * never mistaken for the one being looked for. The line of the deepest
     * segment that could be located is returned, or 0 when even the first one
     * could not be.
     *
     * A node written in flow notation ("{foo: bar}") holds its children on its
     * own line, so the line of the flow container is returned for them.
     */
    private static function locateLine(string $content, string $pointer): int
    {
        $segments = [];
        foreach (explode('/', trim($pointer, '/')) as $segment) {
            if ('' !== $segment) {
                $segments[] = str_replace(['~1', '~0'], ['/', '~'], $segment);
            }
        }

        $lines = explode("\n", $content);
        $line = 0;
        // Lines the children of the current node span, and the indentation of the node itself.
        $start = 0;
        $end = \count($lines);
        $indent = -1;

        foreach ($segments as $segment) {
            $located = ctype_digit($segment) ? self::locateItem($lines, (int) $segment, $start, $end, $indent) : null;
            // A digit that does not match a sequence item can still be a mapping key, as in "404: Not Found".
            $located ??= self::locateKey($lines, $segment, $start, $end, $indent);

            if (null === $located) {
                // The deepest ancestor located is a better answer than a key looked up further down.
                break;
            }

            [$line, $start, $end, $indent] = $located;
        }

        return $line;
    }

    /**
     * Locates a mapping key among the direct children of a node.
     *
     * @param list<string> $lines
     *
     * @return array{int, int, int, int}|null The 1-based line of the key, then the lines its own children span and its indentation
     */
    private static function locateKey(array $lines, string $key, int $start, int $end, int $indent): ?array
    {
        $quoted = preg_quote($key, '/');
        $pattern = '/^\s*(?:"'.$quoted.'"|\''.$quoted.'\'|'.$quoted.')\s*:(?:\s|$)/';
        $childIndent = null;

        foreach (self::significantLines($lines, $start, $end) as $i => $lineIndent) {
            if ($lineIndent <= $indent) {
                // A line at the indentation of the node itself ends it.
                break;
            }

            // Only the shallowest level in the range holds the direct children.
            $childIndent ??= $lineIndent;

            if ($lineIndent === $childIndent && preg_match($pattern, $lines[$i])) {
                return [$i + 1, $i + 1, self::endOfNode($lines, $i + 1, $end, $lineIndent, true), $lineIndent];
            }
        }

        return null;
    }

    /**
     * Locates the nth item of a sequence among the direct children of a node.
     *
     * @param list<string> $lines
     *
     * @return array{int, int, int, int}|null The 1-based line of the item, then the lines its own children span and its indentation
     */
    private static function locateItem(array &$lines, int $index, int $start, int $end, int $indent): ?array
    {
        $itemIndent = null;
        $found = 0;

        foreach (self::significantLines($lines, $start, $end) as $i => $lineIndent) {
            $isItem = self::isSequenceItem($lines[$i]);

            // A sequence may be indented at the level of its parent key, so only a
            // line that is not an item ends the node at that level.
            if ($lineIndent < $indent || ($lineIndent === $indent && !$isItem)) {
                break;
            }

            $itemIndent ??= $lineIndent;

            if ($lineIndent !== $itemIndent || !$isItem || $found++ !== $index) {
                continue;
            }

            $childrenEnd = self::endOfNode($lines, $i + 1, $end, $lineIndent, false);

            // Blank out the dash so a key written on the item line ("- name: a") is
            // seen as a child of the item, indented past it.
            $lines[$i] = substr_replace($lines[$i], ' ', strpos($lines[$i], '-'), 1);

            return [$i + 1, $i, $childrenEnd, $lineIndent];
        }

        return null;
    }

    /**
     * Returns the line the node starting at $start and indented at $indent ends on, exclusive.
     *
     * @param list<string> $lines
     */
    private static function endOfNode(array $lines, int $start, int $end, int $indent, bool $flushSequenceContinues): int
    {
        foreach (self::significantLines($lines, $start, $end) as $i => $lineIndent) {
            if ($lineIndent > $indent) {
                continue;
            }

            if ($lineIndent === $indent && $flushSequenceContinues && self::isSequenceItem($lines[$i])) {
                continue;
            }

            return $i;
        }

        return $end;
    }

    /**
     * Yields the indentation of each line in the range that carries content, keyed by line index.
     *
     * @param list<string> $lines
     *
     * @return iterable<int, int>
     */
    private static function significantLines(array $lines, int $start, int $end): iterable
    {
        for ($i = $start; $i < $end; ++$i) {
            $trimmed = ltrim($lines[$i], " \t");

            if ('' === $trimmed || '#' === $trimmed[0]) {
                continue;
            }

            yield $i => \strlen($lines[$i]) - \strlen($trimmed);
        }
    }

    private static function isSequenceItem(string $line): bool
    {
        return (bool) preg_match('/^\s*-(?:\s|$)/', $line);
    }
}
