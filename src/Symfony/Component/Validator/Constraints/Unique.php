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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Yevgeniy Zholkevskiy <zhenya.zholkevskiy@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Unique extends Constraint
{
    public const IS_NOT_UNIQUE = '7911c98d-b845-4da0-94b7-a8dac36bc55a';

    public array|string $fields = [];

    protected const ERROR_NAMES = [
        self::IS_NOT_UNIQUE => 'IS_NOT_UNIQUE',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'This collection should contain only unique elements.';
    /** @var callable|null */
    public $normalizer;

    /**
     * @param array|string $fields the combination of fields that must contain unique values or a set of options
     */
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
        array|string|null $fields = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->normalizer = $normalizer ?? $this->normalizer;
        $this->fields = $fields ?? $this->fields;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
