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

trigger_error('The '.__NAMESPACE__.'\FalseValidator class is deprecated since version 2.7 and will be removed in 3.0. Use the IsFalseValidator class in the same namespace instead.', E_USER_DEPRECATED);

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use IsFalseValidator instead.
 */
class FalseValidator extends IsFalseValidator {}
