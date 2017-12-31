<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

@trigger_error('The '.__NAMESPACE__.'\DummyException class is deprecated since Symfony 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 */
class DummyException extends \ErrorException
{
}
