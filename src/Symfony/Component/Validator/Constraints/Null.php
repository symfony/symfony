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

trigger_error('The '.__NAMESPACE__.'\Null class is deprecated since version 2.7 and will be removed in 3.0. Use the IsNull class in the same namespace instead.', E_USER_DEPRECATED);

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use IsNull instead.
 */
class Null extends IsNull
{
}
