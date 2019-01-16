<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SubRequestServiceResolutionController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $path['_controller'] = self::class.'::fragmentAction';
        $subRequest = $request->duplicate([], null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    public function fragmentAction(LoggerInterface $logger)
    {
        return new Response('---');
    }
}
