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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 */
class Isbn extends Constraint
{
    public $isbn10Message = 'This value is not a valid ISBN-10.';
    public $isbn13Message = 'This value is not a valid ISBN-13.';
    public $bothIsbnMessage = 'This value is neither a valid ISBN-10 nor a valid ISBN-13.';
    public $isbn10;
    public $isbn13;

    public function __construct($options = null)
    {
        if (null !== $options && !is_array($options)) {
            $options = array(
                'isbn10' => $options,
                'isbn13' => $options,
            );
        }

        parent::__construct($options);

        if (null === $this->isbn10 && null === $this->isbn13) {
            throw new MissingOptionsException(sprintf('Either option "isbn10" or "isbn13" must be given for constraint "%s".', __CLASS__), array('isbn10', 'isbn13'));
        }
    }
}
