<?php

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Exception\UnparsableInputException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

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
        $response = null;
        $reason = null;

        if ($throwable instanceof UnparsableInputException) {
            $reason = $throwable->getMessage();
            $response = new Response($this->serializer->serialize(['message' => 'Invalid input'], $format), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($throwable instanceof ValidationFailedException) {
            $data = [
                'title' => 'Validation Failed',
                'errors' => [],
            ];

            foreach ($throwable->getViolations() as $violation) {
                $data['errors'][] = [
                    'propertyPath' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ];
            }
            $response = new Response($this->serializer->serialize($data, $format), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null === $response) {
            return;
        }

        $this->logger->info('Invalid input rejected: "{reason}"', [
            'reason' => $reason,
        ]);

        $event->setResponse($response);
    }
}
