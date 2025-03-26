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
 * Validates that a value is a valid slug.
 *
 * @author Raffaele Carelle <raffaele.carelle@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Slug extends Constraint
{
    public const NOT_SLUG_ERROR = '14e6df1e-c8ab-4395-b6ce-04b132a3765e';

    public string $message = 'This value is not a valid slug.';
    public string $regex = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    #[HasNamedArguments]
    public function __construct(
        ?string $regex = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->regex = $regex ?? $this->regex;
    }
}
