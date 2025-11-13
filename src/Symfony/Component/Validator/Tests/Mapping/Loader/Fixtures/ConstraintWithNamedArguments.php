<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader\Fixtures;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class ConstraintWithNamedArguments extends Constraint
{
    public $choices;

    #[HasNamedArguments]
    public function __construct(array|string|null $choices = [], ?array $groups = null)
    {
        parent::__construct(null, $groups);

        $this->choices = $choices;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
