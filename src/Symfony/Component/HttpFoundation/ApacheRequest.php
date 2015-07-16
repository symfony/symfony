<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Request represents an HTTP request from an Apache server.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 2.8, to be removed in 3.0. The apache request is primarily useful
 * with the apache route dumper which is already deprecated
 */
class ApacheRequest extends Request
{
}
