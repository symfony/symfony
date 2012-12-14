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

/**
 * @Annotation
 *
 * @api
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
 *             {@link OneOf} instead.
 */
class Choice extends OneOf
{
    public function __construct($options = null)
    {
        trigger_error('Choice constraint is deprecated since version 2.2 and will be removed in 2.3. Use OneOf instead', E_USER_DEPRECATED);
        parent::__construct($options);
    }
}
