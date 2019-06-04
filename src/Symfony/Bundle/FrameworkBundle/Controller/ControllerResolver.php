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
 *
 * @final since Symfony 4.4
 */
class ControllerResolver extends ContainerControllerResolver
{
    /**
     * @deprecated since Symfony 4.4
     */
    protected $parser;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(ContainerInterface $container, $logger = null)
    {
        if ($logger instanceof ControllerNameParser) {
            @trigger_error(sprintf('Passing a "%s" instance as 2nd argument to "%s()" is deprecated since Symfony 4.4, pass a "%s" instance or null instead.', ControllerNameParser::class, __METHOD__, LoggerInterface::class), E_USER_DEPRECATED);
            $this->parser = $logger;
            $logger = 2 < \func_num_args() ? func_get_arg(2) : null;
        } elseif (2 < \func_num_args() && func_get_arg(2) instanceof ControllerNameParser) {
            $this->parser = func_get_arg(2);
        } elseif ($logger && !$logger instanceof LoggerInterface) {
            throw new \TypeError(sprintf('Argument 2 of "%s()" must be an instance of "%s" or null, "%s" given.', __METHOD__, LoggerInterface::class, \is_object($logger) ? \get_class($logger) : \gettype($logger)), E_USER_DEPRECATED);
        }

        parent::__construct($container, $logger);
    }

    /**
     * {@inheritdoc}
     */
    protected function createController($controller)
    {
        if ($this->parser && false === strpos($controller, '::') && 2 === substr_count($controller, ':')) {
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
        return $this->configureController(parent::instantiateController($class), $class);
    }

    private function configureController($controller, string $class)
    {
        if ($controller instanceof ContainerAwareInterface) {
            $controller->setContainer($this->container);
        }
        if ($controller instanceof AbstractController) {
            if (null === $previousContainer = $controller->setContainer($this->container)) {
                @trigger_error(sprintf('Auto-injection of the container for "%s" is deprecated since Symfony 4.2. Configure it as a service instead.', $class), E_USER_DEPRECATED);
            // To be uncommented on Symfony 5:
                //throw new \LogicException(sprintf('"%s" has no container set, did you forget to define it as a service subscriber?', $class));
            } else {
                $controller->setContainer($previousContainer);
            }
        }

        return $controller;
    }
}
