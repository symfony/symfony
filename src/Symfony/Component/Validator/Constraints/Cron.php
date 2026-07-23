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

use Cron\CronExpression;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Validates that a value is a valid cron expression.
 *
 * @see https://en.wikipedia.org/wiki/Cron
 *
 * @author Erfan Momeni <erfamm5@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Cron extends Constraint
{
    public const INVALID_FORMAT_ERROR = '4ef85758-9c41-4f9b-9b27-9c0b6f1f6f0e';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    public const MODE_STANDARD = 'standard';
    public const MODE_ALIASES = 'aliases';
    public const MODE_HASHED = 'hashed';

    private const MODES = [
        self::MODE_STANDARD,
        self::MODE_ALIASES,
        self::MODE_HASHED,
    ];

    public string $message = 'This value is not a valid cron expression.';
    public string $mode = self::MODE_ALIASES;

    /**
     * @param string[]|null     $groups
     * @param self::MODE_*|null $mode   The accepted cron expression flavor (defaults to {@see self::MODE_ALIASES})
     */
    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $mode = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->mode = $mode ?? $this->mode;

        if (!\in_array($this->mode, self::MODES, true)) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" validation mode is not supported. Use one of "%s".', $this->mode, implode('", "', self::MODES)));
        }

        if (!class_exists(CronExpression::class)) {
            throw new LogicException(\sprintf('The "dragonmantank/cron-expression" package is required to use the "%s" constraint. Try running "composer require dragonmantank/cron-expression".', __CLASS__));
        }
    }
}
