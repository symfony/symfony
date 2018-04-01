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

use Psr\Log\LoggerInterface;
use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\HttpKernelInterface;

class SubRequestServiceResolutionController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $path['_controller'] = self::class.'::fragmentAction';
        $subRequest = $request->duplicate(array(), null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    public function fragmentAction(LoggerInterface $logger)
    {
        return new Response('---');
    }
}
