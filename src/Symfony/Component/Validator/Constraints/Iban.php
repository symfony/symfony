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
 */
class Iban extends Constraint
{
    const ERROR = '403389e4-59d7-4942-ad9c-b5ba5a0a6a92';

    public $message = 'This is not a valid International Bank Account Number (IBAN).';
}
