<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallPostAuthenticationBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class SecureController
{
    const CONTENT = 'CUSTOM AUTHORIZED CONTENT';

    public function indexAction()
    {
        return new Response(self::CONTENT);
    }
}
