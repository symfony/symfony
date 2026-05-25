<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\QuestionAnsweredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates Question answers (user input) using the Validator component.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class ValidateQuestionInputListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function onQuestionAnswered(QuestionAnsweredEvent $event): void
    {
        $violations = $this->validator->validate($event->value, $event->constraints);

        foreach ($violations as $violation) {
            $event->addViolation($violation->getMessage());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::QUESTION_ANSWERED => 'onQuestionAnswered'];
    }
}
