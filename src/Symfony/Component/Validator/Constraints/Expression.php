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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\RuntimeException;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Expression extends Constraint
{
    const EXPRESSION_FAILED_ERROR = '6b3befbc-2f01-4ddf-be21-b57898905284';

    protected static $errorNames = array(
        self::EXPRESSION_FAILED_ERROR => 'EXPRESSION_FAILED_ERROR',
    );

    public $message = 'This value is not valid.';
    public $expression;
    public $dataPath;

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null !== $this->dataPath && '' !== $this->dataPath && !class_exists(PropertyAccess::class)) {
            throw new RuntimeException('Unable to use expressions with data path as the Symfony PropertyAccess component is not installed.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'expression';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return array('expression');
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return array(self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT);
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'validator.expression';
    }
}
