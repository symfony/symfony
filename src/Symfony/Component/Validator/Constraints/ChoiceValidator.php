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
 * ChoiceValidator validates that the value is one of the expected values.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
 *             {@link OneOfValidator} instead.
 */
class ChoiceValidator extends OneOfValidator
{
    public function __construct($options = null)
    {
        trigger_error('ChoiceValidator is deprecated since version 2.2 and will be removed in 2.3. Use OneOfValidator instead', E_USER_DEPRECATED);
        parent::__construct($options);
    }
}
