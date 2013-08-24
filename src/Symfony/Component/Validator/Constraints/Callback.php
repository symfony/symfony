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
 *
 * @since v2.0.0
 */
class Callback extends Constraint
{
    public $methods;

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getRequiredOptions()
    {
        return array('methods');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getDefaultOption()
    {
        return 'methods';
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
