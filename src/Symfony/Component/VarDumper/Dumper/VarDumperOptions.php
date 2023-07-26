<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Caster\ScalarStub;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class VarDumperOptions
{
    public const FORMAT = '_format';
    public const TRACE = '_trace';
    public const MAX_ITEMS = '_max_items';
    public const MIN_DEPTH = '_min_depth';
    public const MAX_STRING = '_max_string';
    public const MAX_DEPTH = '_max_depth';
    public const MAX_ITEMS_PER_DEPTH = '_max_items_per_depth';
    public const THEME = '_theme';
    public const FLAGS = '_flags';
    public const CHARSET = '_charset';

    public const AVAILABLE_OPTIONS = [
        self::FORMAT,
        self::TRACE,
        self::MAX_ITEMS,
        self::MIN_DEPTH,
        self::MAX_STRING,
        self::MAX_DEPTH,
        self::MAX_ITEMS_PER_DEPTH,
        self::THEME,
        self::FLAGS,
        self::CHARSET,
    ];

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_filter(
            $options,
            static fn (mixed $key): bool => \in_array($key, self::AVAILABLE_OPTIONS),
            \ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @template T
     *
     * @param T ...$vars
     *
     * @return T
     */
    public function dump(mixed ...$vars): mixed
    {
        if (!$vars) {
            $vars = [new ScalarStub('🐛')];
        }

        return dump(...$vars + $this->options);
    }

    public function dd(mixed ...$vars): never
    {
        dd(...$vars + $this->options);
    }

    public function format(?string $format): static
    {
        $this->options[self::FORMAT] = $format;

        return $this;
    }

    public function trace(bool|int $trace = true): static
    {
        $this->options[self::TRACE] = $trace;

        return $this;
    }

    public function maxItems(int $maxItems): static
    {
        $this->options[self::MAX_ITEMS] = $maxItems;

        return $this;
    }

    public function minDepth(int $minDepth): static
    {
        $this->options[self::MIN_DEPTH] = $minDepth;

        return $this;
    }

    public function maxString(int $maxString): static
    {
        $this->options[self::MAX_STRING] = $maxString;

        return $this;
    }

    public function maxDepth(int $maxDepth): static
    {
        $this->options[self::MAX_DEPTH] = $maxDepth;

        return $this;
    }

    public function maxItemsPerDepth(int $maxItemsPerDepth): static
    {
        $this->options[self::MAX_ITEMS_PER_DEPTH] = $maxItemsPerDepth;

        return $this;
    }

    public function theme(?string $theme): static
    {
        $this->options[self::THEME] = $theme ?? 'dark';

        return $this;
    }

    /**
     * @param AbstractDumper::DUMP_* $flags
     */
    public function flags(int $flags): static
    {
        $this->options[self::FLAGS] = $flags;

        return $this;
    }

    /**
     * Display arrays with short form (omitting elements count and `array` prefix).
     */
    public function showLightArray(): static
    {
        $this->options[self::FLAGS] = ($this->options[self::FLAGS] ?? 0) | AbstractDumper::DUMP_LIGHT_ARRAY;

        return $this;
    }

    /**
     * Display string length, just before its value.
     */
    public function showStringLength(): static
    {
        $this->options[self::FLAGS] = ($this->options[self::FLAGS] ?? 0) | AbstractDumper::DUMP_STRING_LENGTH;

        return $this;
    }

    /**
     * Display a comma at the end of the line of an array element.
     */
    public function showCommaSeparator(): static
    {
        $this->options[self::FLAGS] = ($this->options[self::FLAGS] ?? 0) | AbstractDumper::DUMP_COMMA_SEPARATOR;

        return $this;
    }

    /**
     * Display a trailing comma after the last element of an array.
     */
    public function showTrailingComma(): static
    {
        $this->options[self::FLAGS] = ($this->options[self::FLAGS] ?? 0) | AbstractDumper::DUMP_TRAILING_COMMA;

        return $this;
    }

    public function charset(string $charset): static
    {
        $this->options[self::CHARSET] = $charset;

        return $this;
    }

    public function get(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
