<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Request\ParamConverter\ConverterManager;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * Converts \ReflectionParameters for Controller actions into Objects if the \ReflectionParameter have a class
 * (Typehinted).
 *
 * The filterController method must be connected to the core.controller event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.org>
 * @author Henrik Bjornskov <hb@peytz.dk>
 */
class ParamConverterListener
{
    /**
     * @var ConverterManager
     */
    protected $manager;

    /**
     * @param ConverterManager   $manager
     */
    public function __construct(ConverterManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param  Event $event
     * @param  mixed $controller
     *
     * @return mixed
     *
     * @throws NotFoundHttpException
     */
    public function filterController(Event $event, $controller)
    {
        if (!is_array($controller)) {
            return $controller;
        }

        $request = $event->get('request');
        $method  = new \ReflectionMethod($controller[0], $controller[1]);

        foreach ($method->getParameters() as $param) {
            if (null !== $param->getClass() && false === $request->attributes->has($param->getName())) {
                try {
                    $this->manager->apply($request, $param);
                } catch (\InvalidArgumentException $e) {
                    if (false === $param->isOptional()) {
                        throw new NotFoundHttpException(sprintf('Unable to convert parameter "%s".', $param->getName()), $e);
                    }
                }
            }
        }

        return $controller;
    }
}
