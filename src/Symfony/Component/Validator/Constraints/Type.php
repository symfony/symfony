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
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Type extends Constraint
{
    const ERROR = 'f42711f9-d10d-4272-9b1c-76de264f642c';

    public $message = 'This value should be of type {{ type }}.';
    public $type;

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'type';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('type');
    }
}
