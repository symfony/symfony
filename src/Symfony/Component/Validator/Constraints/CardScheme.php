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
 * Metadata for the CardSchemeValidator.
 *
 * @Annotation
 *
 * @since v2.2.0
 */
class CardScheme extends Constraint
{
    public $message = 'Unsupported card type or invalid card number.';
    public $schemes;

    /**
     * @since v2.2.0
     */
    public function getDefaultOption()
    {
        return 'schemes';
    }

    /**
     * @since v2.2.0
     */
    public function getRequiredOptions()
    {
        return array('schemes');
    }
}
