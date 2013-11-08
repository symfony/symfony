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
 *
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 */
class Isbn extends Constraint
{
    const ERROR = 'af6f41b0-3626-4b78-96a3-a6027832d1cb';
    const ERROR_ISBN10 = '7665fcb0-e6bf-4d31-8159-f9cf713bae5f';
    const ERROR_ISBN13 = '6aa1c63f-c281-4741-bb12-d8505011414f';

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
