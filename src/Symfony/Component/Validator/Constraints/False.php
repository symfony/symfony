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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class False extends Constraint
{
    const ERROR = '890770b4-e5c4-49e5-a285-b480ab42b43a';

    public $message = 'This value should be false.';
}
