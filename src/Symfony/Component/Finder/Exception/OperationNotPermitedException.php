<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Exception;

@trigger_error('The '.__NAMESPACE__.'\OperationNotPermitedException class is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @deprecated since 2.8, to be removed in 3.0.
 */
class OperationNotPermitedException extends AdapterFailureException
{
}
