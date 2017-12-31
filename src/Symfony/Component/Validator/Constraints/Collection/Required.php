<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints\Collection;

@trigger_error('The '.__NAMESPACE__.'\Required class is deprecated since Symfony 2.3 and will be removed in 3.0. Use the Symfony\Component\Validator\Constraints\Required class instead.', E_USER_DEPRECATED);

use Symfony\Component\Validator\Constraints\Required as BaseRequired;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Validator\Constraints\Required} instead.
 */
class Required extends BaseRequired
{
}
