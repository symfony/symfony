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
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Callback extends Constraint
{
    /**
     * @var string|callable
     */
    public $callback;

    /**
     * @var array
     *
     * @deprecated since version 2.4, to be removed in 3.0.
     */
    public $methods;

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        // Invocation through annotations with an array parameter only
        if (is_array($options) && 1 === count($options) && isset($options['value'])) {
            $options = $options['value'];
        }

        if (is_array($options) && isset($options['methods'])) {
            @trigger_error('The "methods" option of the '.__CLASS__.' class is deprecated since Symfony 2.4 and will be removed in 3.0. Use the "callback" option instead.', E_USER_DEPRECATED);
        }

        if (is_array($options) && !isset($options['callback']) && !isset($options['methods']) && !isset($options['groups']) && !isset($options['payload'])) {
            if (is_callable($options) || !$options) {
                $options = array('callback' => $options);
            } else {
                // @deprecated, to be removed in 3.0
                $options = array('methods' => $options);
            }
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'callback';
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return array(self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT);
    }
}
