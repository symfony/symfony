<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\HttpFoundation\Response;

class ProfilerController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction()
    {
        return new Response('Hello');
    }
}
