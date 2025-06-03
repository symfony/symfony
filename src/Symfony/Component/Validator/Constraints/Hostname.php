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

/**
 * Validates that a value is a valid host name.
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Hostname extends Constraint
{
    public const INVALID_HOSTNAME_ERROR = '7057ffdb-0af4-4f7e-bd5e-e9acfa6d7a2d';

    protected const ERROR_NAMES = [
        self::INVALID_HOSTNAME_ERROR => 'INVALID_HOSTNAME_ERROR',
    ];

    public string $message = 'This value is not a valid hostname.';
    public bool $requireTld = true;

    /**
     * @param array<string,mixed>|null $options
     * @param bool|null                $requireTld Whether to require the hostname to include its top-level domain (defaults to true)
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?bool $requireTld = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->requireTld = $requireTld ?? $this->requireTld;
    }
}
