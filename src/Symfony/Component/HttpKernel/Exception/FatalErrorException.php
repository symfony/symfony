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

@trigger_error('The '.__NAMESPACE__.'\FatalErrorException class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Debug\Exception\FatalErrorException class instead.', E_USER_DEPRECATED);

/*
 * Fatal Error Exception.
 *
 * @author Konstanton Myakshin <koc-dp@yandex.ru>
 *
 * @deprecated since version 2.3, to be removed in 3.0. Use the same class from the Debug component instead.
 */
class_exists('Symfony\Component\Debug\Exception\FatalErrorException');
