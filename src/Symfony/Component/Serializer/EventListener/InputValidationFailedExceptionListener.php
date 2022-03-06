<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\InputValidationFailedException;

/**
 * Works in duo with Symfony\Bundle\FrameworkBundle\ArgumentResolver\UserInputResolver.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InputValidationFailedExceptionListener
{
    public function __construct(private SerializerInterface $serializer, private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $format = $event->getRequest()->attributes->get('_format', 'json');

        if (!$throwable instanceof InputValidationFailedException) {
            return;
        }

        $response = new Response($this->serializer->serialize($throwable->getViolations(), $format), Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->logger->info('Invalid input rejected: "{reason}"', ['reason' => (string) $throwable->getViolations()]);

        $event->setResponse($response);
    }
}
