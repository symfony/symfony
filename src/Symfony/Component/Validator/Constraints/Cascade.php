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

/**
 * Validates a whole class, including nested objects in properties.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Cascade extends Constraint
{
    public array $exclude = [];

    /**
     * @param non-empty-string[]|non-empty-string|null $exclude Properties excluded from validation
     */
    public function __construct(array|string|null $exclude = null)
    {
        parent::__construct();

        $this->exclude = array_flip((array) $exclude);
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
