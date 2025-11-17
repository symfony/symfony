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

use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait ZeroComparisonConstraintTrait
{
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(0, null, $message, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return parent::class.'Validator';
    }
}
