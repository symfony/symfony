<?php

namespace Symfony\Component\HttpKernel\EventListener;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationInterface;
use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationList;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ControllerListener class parses annotation blocks located in
 * controller classes and creates a list of configurations to be used
 * on others event listeners.
 *
 * @author Tales Santos <tales.augusto.santos@gmail.com>
 */
class ControllerListener implements EventSubscriberInterface
{
    /**
     * @var Reader
     */
    private $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return;
        }

        $reflectionObject = new \ReflectionObject($controller[0]);
        $reflectionMethod = $reflectionObject->getMethod($controller[1]);

        $configurations = new ConfigurationList();

        $this->appendConfigurations($configurations, $this->reader->getClassAnnotations($reflectionObject));
        $this->appendConfigurations($configurations, $this->reader->getMethodAnnotations($reflectionMethod));

        $event->getRequest()->attributes->set('_configurations', $configurations);
    }

    private function appendConfigurations(ConfigurationList $configurations, array $annotations): void
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof ConfigurationInterface) {
                $configurations->add($annotation);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }
}
