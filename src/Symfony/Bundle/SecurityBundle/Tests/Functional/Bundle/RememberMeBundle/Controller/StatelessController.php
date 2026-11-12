<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RememberMeBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

class StatelessController
{
    public function __invoke()
    {
        return new Response('stateless');
    }
}
