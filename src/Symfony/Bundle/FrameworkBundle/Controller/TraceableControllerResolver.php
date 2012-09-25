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

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Debug\Stopwatch;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;

/**
 * TraceableControllerResolver.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableControllerResolver extends ControllerResolver
{
    private $stopwatch;

    /**
     * Constructor.
     *
     * @param ContainerInterface   $container A ContainerInterface instance
     * @param ControllerNameParser $parser    A ControllerNameParser instance
     * @param Stopwatch            $stopwatch A Stopwatch instance
     * @param LoggerInterface      $logger    A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, ControllerNameParser $parser, Stopwatch $stopwatch, LoggerInterface $logger = null)
    {
        parent::__construct($container, $parser, $logger);

        $this->stopwatch = $stopwatch;
    }

    /**
     * @{inheritdoc}
     */
    public function getController(Request $request)
    {
        $e = $this->stopwatch->start('controller.get_callable');

        $ret = parent::getController($request);

        $e->stop();

        return $ret;
    }

    /**
     * @{inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        $e = $this->stopwatch->start('controller.get_arguments');

        $ret = parent::getArguments($request, $controller);

        $e->stop();

        return $ret;
    }
}
