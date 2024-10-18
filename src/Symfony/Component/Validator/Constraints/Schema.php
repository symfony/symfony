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
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Yaml\Parser;

/**
 * @author Benjamin Georgeault <bgeorgeault@wedgesama.fr>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Schema extends Composite
{
    public const JSON = 'JSON';
    public const YAML = 'YAML';

    public const INVALID_ERROR = 'f8925b90-edfd-4364-a17a-f34a60f24b26';

    public string $format;

    protected const ERROR_NAMES = [
        self::INVALID_ERROR => 'INVALID_ERROR',
    ];

    private static array $allowedTypes = [
        self::JSON,
        self::YAML,
    ];

    /**
     * @param array<Constraint>|Constraint $constraints
     */
    #[HasNamedArguments]
    public function __construct(
        string $format,
        public array|Constraint $constraints = [],
        public ?string $invalidMessage = 'Cannot apply schema validation, this value does not respect format.',
        ?array $groups = null,
        mixed $payload = null,
        public int $flags = 0,
        public ?int $depth = null,
    ) {
        $this->format = $format = strtoupper($format);

        if (!\in_array($format, static::$allowedTypes)) {
            throw new InvalidArgumentException(\sprintf('The "format" parameter value is not valid. It must contain one or more of the following values: "%s".', implode(', ', self::$allowedTypes)));
        }

        if (self::YAML === $format && !class_exists(Parser::class)) {
            throw new LogicException('The Yaml component is required to use the Yaml constraint. Try running "composer require symfony/yaml".');
        }

        parent::__construct([
            'constraints' => $constraints,
        ], $groups, $payload);
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
