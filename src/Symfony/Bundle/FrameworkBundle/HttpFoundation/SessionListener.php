<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\HttpFoundation;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * SessionListener.
 *
 * Saves session in test environment.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class SessionListener
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Boolean
     */
    private $master = false;

    /**
     * Assigns request and its type on 'core.request' event
     *
     * @param EventInterface $event
     */
    public function handle(EventInterface $event)
    {
        $this->request = $event->get('request');
        $this->master = HttpKernelInterface::MASTER_REQUEST === $event->get('request_type');
    }

    /**
     * Checks if session was initialized and saves if current request is master
     * Runs on 'core.response' in test environment
     *
     * @param EventInterface $event
     * @param Response $response
     *
     * @return Response
     */
    public function filter(EventInterface $event, Response $response)
    {
        if (isset($this->request) && $this->master && null !== $this->request->getSession()) {
            $this->request->getSession()->save();
        }

        return $response;
    }

    /**
     * Returns true is current request is master request
     *
     * @return Boolean
     */
    public function isMaster()
    {
        return $this->master;
    }
}
