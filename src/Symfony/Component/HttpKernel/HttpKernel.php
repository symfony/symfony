<?php

namespace Symfony\Component\HttpKernel;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HttpKernel notifies events to convert a Request object to a Response one.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpKernel extends BaseHttpKernel
{
    protected $container;

    /**
     * Constructor
     *
     * @param ContainerInterface          $container An ContainerInterface instance
     * @param EventDispatcher             $dispatcher An event dispatcher instance
     * @param ControllerResolverInterface $resolver A ControllerResolverInterface instance
     */
    public function __construct(ContainerInterface $container, EventDispatcher $dispatcher, ControllerResolverInterface $resolver)
    {
        $this->container = $container;

        parent::__construct($dispatcher, $resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $masterRequest = HttpKernelInterface::MASTER_REQUEST === $type ? $request : $this->container->get('request');

        $this->container->set('request', $request);

        $response = parent::handle($request, $type, $catch);

        $this->container->set('request', $masterRequest);

        return $response;
    }
}
