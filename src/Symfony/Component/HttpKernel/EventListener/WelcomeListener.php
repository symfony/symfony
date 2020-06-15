<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\NoConfigurationException;

/**
 * @internal
 */
final class WelcomeListener implements EventSubscriberInterface
{
    private $projectDir;

    public function __construct(string $projectDir = null)
    {
        $this->projectDir = $projectDir;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!($e = $event->getThrowable()) instanceof NotFoundHttpException) {
            return;
        }

        if ($e->getPrevious() instanceof NoConfigurationException) {
            $event->setResponse($this->createWelcomeResponse());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -64],
        ];
    }

    private function createWelcomeResponse(): Response
    {
        $version = Kernel::VERSION;
        $projectDir = realpath($this->projectDir).\DIRECTORY_SEPARATOR;
        $docVersion = substr(Kernel::VERSION, 0, 3);

        ob_start();
        include \dirname(__DIR__).'/Resources/welcome.html.php';

        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
