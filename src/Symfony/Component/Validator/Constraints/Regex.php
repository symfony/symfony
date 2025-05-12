<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Validates that a value matches a regular expression.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Regex extends Constraint
{
    public const REGEX_FAILED_ERROR = 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3';

    protected const ERROR_NAMES = [
        self::REGEX_FAILED_ERROR => 'REGEX_FAILED_ERROR',
    ];

    public string $message = 'This value is not valid.';
    public ?string $pattern = null;
    public ?string $htmlPattern = null;
    public bool $match = true;
    /** @var callable|null */
    public $normalizer;

    /**
     * @param string|array<string,mixed>|null $pattern     The regular expression to match
     * @param string|null                     $htmlPattern The pattern to use in the HTML5 pattern attribute
     * @param bool|null                       $match       Whether to validate the value matches the configured pattern or not (defaults to true)
     * @param string[]|null                   $groups
     * @param array<string,mixed>|null        $options
     */
    #[HasNamedArguments]
    public function __construct(
        string|array|null $pattern,
        ?string $message = null,
        ?string $htmlPattern = null,
        ?bool $match = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $options = null,
    ) {
        if (\is_array($pattern)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($pattern, $options ?? []);
        } elseif (null !== $pattern) {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            } else {
                $options = [];
            }

            $options['value'] = $pattern;
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->htmlPattern = $htmlPattern ?? $this->htmlPattern;
        $this->match = $match ?? $this->match;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(\sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }

    public function getDefaultOption(): ?string
    {
        return 'pattern';
    }

    public function getRequiredOptions(): array
    {
        return ['pattern'];
    }

    /**
     * Converts the htmlPattern to a suitable format for HTML5 pattern.
     * Example: /^[a-z]+$/ would be converted to [a-z]+
     * However, if options are specified, it cannot be converted.
     *
     * @see http://dev.w3.org/html5/spec/single-page.html#the-pattern-attribute
     */
    public function getHtmlPattern(): ?string
    {
        // If htmlPattern is specified, use it
        if (null !== $this->htmlPattern) {
            return $this->htmlPattern ?: null;
        }

        // Quit if delimiters not at very beginning/end (e.g. when options are passed)
        if ($this->pattern[0] !== $this->pattern[\strlen($this->pattern) - 1]) {
            return null;
        }

        $delimiter = $this->pattern[0];

        // Unescape the delimiter
        $pattern = str_replace('\\'.$delimiter, $delimiter, substr($this->pattern, 1, -1));

        // If the pattern is inverted, we can wrap it in
        // ((?!pattern).)*
        if (!$this->match) {
            return '((?!'.$pattern.').)*';
        }

        // If the pattern contains an or statement, wrap the pattern in
        // .*(pattern).* and quit. Otherwise we'd need to parse the pattern
        if (str_contains($pattern, '|')) {
            return '.*('.$pattern.').*';
        }

        // Trim leading ^, otherwise prepend .*
        $pattern = '^' === $pattern[0] ? substr($pattern, 1) : '.*'.$pattern;

        // Trim trailing $, otherwise append .*
        return '$' === $pattern[\strlen($pattern) - 1] ? substr($pattern, 0, -1) : $pattern.'.*';
    }
}
