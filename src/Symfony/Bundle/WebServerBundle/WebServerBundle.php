<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @deprecated since Symfony 4.4, to be removed in 5.0; the new Symfony local server has more features, you can use it instead.
 */
class WebServerBundle extends Bundle
{
    public function boot()
    {
        @trigger_error('Using the WebserverBundle is deprecated since Symfony 4.4. The new Symfony local server has more features, you can use it instead.', E_USER_DEPRECATED);
    }
}
