<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

@trigger_error('The '.__NAMESPACE__.'\FlattenException class is deprecated since Symfony 2.3 and will be removed in 3.0. Use the Symfony\Component\Debug\Exception\FlattenException class instead.', E_USER_DEPRECATED);

/*
 * FlattenException wraps a PHP Exception to be able to serialize it.
 *
 * Basically, this class removes all objects from the trace.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0. Use the same class from the Debug component instead.
 */
class_exists('Symfony\Component\Debug\Exception\FlattenException');
