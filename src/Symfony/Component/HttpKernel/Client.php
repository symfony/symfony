<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" instead.', Client::class, HttpKernelBrowser::class), E_USER_DEPRECATED);

class Client extends HttpKernelBrowser
{
}
