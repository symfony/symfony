<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidationMiddleware implements MiddlewareInterface, EnvelopeAwareInterface
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function handle($message, callable $next)
    {
        $envelope = Envelope::wrap($message);
        $subject = $envelope->getMessage();
        $groups = null;
        /** @var ValidationConfiguration|null $validationConfig */
        if ($validationConfig = $envelope->get(ValidationConfiguration::class)) {
            $groups = $validationConfig->getGroups();
        }

        $violations = $this->validator->validate($subject, null, $groups);
        if (\count($violations)) {
            throw new ValidationFailedException($subject, $violations);
        }

        return $next($message);
    }
}
