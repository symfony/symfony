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

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates that a value is a valid IP address
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Ip extends \Symfony\Component\Validator\Constraint
{
    const V4 = '4';
    const V6 = '6';
    const ALL = 'all';

    static protected $versions = array(
        self::V4,
        self::V6,
        self::ALL,
    );

    public $version = self::V4;

    public $message = 'This is not a valid IP address';

    /**
     * @inheritDoc
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (!in_array($this->version, self::$versions)) {
            throw new ConstraintDefinitionException(sprintf('The option "version" must be one of "%s"', implode('", "', self::$versions)));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
