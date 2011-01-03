<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates that a value is a valid IP address
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
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
}
