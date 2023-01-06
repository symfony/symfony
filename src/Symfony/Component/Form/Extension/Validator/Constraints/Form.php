<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form extends Constraint
{
    public const NOT_SYNCHRONIZED_ERROR = '1dafa156-89e1-4736-b832-419c2e501fca';
    public const NO_SUCH_FIELD_ERROR = '6e5212ed-a197-4339-99aa-5654798a4854';

    protected const ERROR_NAMES = [
        self::NOT_SYNCHRONIZED_ERROR => 'NOT_SYNCHRONIZED_ERROR',
        self::NO_SUCH_FIELD_ERROR => 'NO_SUCH_FIELD_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
