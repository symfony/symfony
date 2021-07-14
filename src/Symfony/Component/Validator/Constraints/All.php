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
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class All extends Composite
{
    public $constraints = [];

    /**
     * {@inheritdoc}
     *
     * @param mixed[]|Constraint[]|Constraint $constraints The nested constraints or an array of options
     */
    public function __construct($constraints = null, array $groups = null)
    {
        if ($constraints instanceof Constraint) {
            $options = ['constraints' => $constraints];
        } elseif (!\is_array($constraints)) {
            throw new \TypeError(sprintf('%s: Parameter #1 is extected to be an array or an instance of %s, %s given.', __METHOD__, Constraint::class, get_debug_type($constraints)));
        } elseif (\array_key_exists('constraints', $constraints) || \array_key_exists('value', $constraints)) {
            $options = $constraints;
        } else {
            $options = ['constraints' => $constraints];
        }

        parent::__construct($options, $groups);
    }

    public function getDefaultOption()
    {
        return 'constraints';
    }

    public function getRequiredOptions()
    {
        return ['constraints'];
    }

    protected function getCompositeOption()
    {
        return 'constraints';
    }
}
