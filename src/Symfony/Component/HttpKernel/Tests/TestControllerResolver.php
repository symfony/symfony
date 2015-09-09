<?php

namespace Symfony\Component\HttpKernel\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class TestControllerResolver extends ControllerResolver
{
    private $controller;

    public function __construct($controller, LoggerInterface $logger = null)
    {
        parent::__construct($logger);
        $this->controller = $controller;
    }

    public function getController(Request $request)
    {
        return array($this->controller, 'callController');
    }
}
