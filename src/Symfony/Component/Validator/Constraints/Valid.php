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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Valid extends Constraint
{
    /**
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use the {@link Traverse} constraint instead.
     */
    public $traverse = true;

    /**
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use the {@link Traverse} constraint instead.
     */
    public $deep = false;

    public function __construct($options = null)
    {
        if (is_array($options) && array_key_exists('groups', $options)) {
            throw new ConstraintDefinitionException(sprintf(
                'The option "groups" is not supported by the constraint %s',
                __CLASS__
            ));
        }

        parent::__construct($options);
    }

    public function getDefaultOption()
    {
        // Traverse is extended for backwards compatibility reasons
        // The parent class should be removed in 3.0
        return null;
    }
}
