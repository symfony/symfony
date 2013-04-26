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
 * @author Steve Müller <st.mueller@dzh-online.de>
 *
 * @api
 */
class Digit extends Constraint
{
    public $message = 'This value does not consist of numeric characters only.';
}
