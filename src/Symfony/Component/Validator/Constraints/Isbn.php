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
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 * @author Manuel Reinhard <manu@sprain.ch>
 *
 * @api
 */
class Isbn extends Constraint
{
    public $isbn10Message   = 'This value is not a valid ISBN-10.';
    public $isbn13Message   = 'This value is not a valid ISBN-13.';
    public $bothIsbnMessage = 'This value is neither a valid ISBN-10 nor a valid ISBN-13.';
    public $type;
    public $message;

    /**
     * @deprecated Deprecated since version 2.3.12, to be removed in 2.5. Use option "type" instead.
     * @var bool
     */
    public $isbn10 = false;

    /**
     * @deprecated Deprecated since version 2.3.12, to be removed in 2.5. Use option "type" instead.
     * @var bool
     */
    public $isbn13 = false;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if ($this->isbn10 || $this->isbn13) {
            trigger_error(
                'The options "isbn10" and "isbn13" are deprecated since version 2.3.12 and will be removed in 2.5. Use option "type" instead.',
                E_USER_DEPRECATED
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'type';
    }
}
