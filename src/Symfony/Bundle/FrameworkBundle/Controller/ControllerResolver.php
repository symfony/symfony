<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ControllerResolver extends ContainerControllerResolver
{
    protected $parser;

    public function __construct(ContainerInterface $container, ControllerNameParser $parser, LoggerInterface $logger = null)
    {
        $this->parser = $parser;

        parent::__construct($container, $logger);
    }

    /**
     * {@inheritdoc}
     */
    protected function createController($controller)
    {
        if (false === strpos($controller, '::') && 2 === substr_count($controller, ':')) {
            // controller in the a:b:c notation then
            $deprecatedNotation = $controller;
            $controller = $this->parser->parse($deprecatedNotation, false);

            @trigger_error(sprintf('Referencing controllers with %s is deprecated since Symfony 4.1. Use %s instead.', $deprecatedNotation, $controller), E_USER_DEPRECATED);
        }

        return parent::createController($controller);
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiateController($class)
    {
        return $this->configureController(parent::instantiateController($class));
    }

    private function configureController($controller)
    {
        if ($controller instanceof ContainerAwareInterface) {
            $controller->setContainer($this->container);
        }
        if ($controller instanceof AbstractController && null !== $previousContainer = $controller->setContainer($this->container)) {
            $controller->setContainer($previousContainer);
        }

        return $controller;
    }
}
