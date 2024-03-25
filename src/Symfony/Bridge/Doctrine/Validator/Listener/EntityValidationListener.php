<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator\Listener;

use Doctrine\Persistence\Event\ManagerEventArgs;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Attribute\DisableAutoValidation;
use Symfony\Bridge\Doctrine\Attribute\EnableAutoValidation;
use Symfony\Bridge\Doctrine\Validator\Listener\Exception\EntityValidationFailedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityValidationListener
{
    /**
     * @var array<class-string, EnableAutoValidation|DisableAutoValidation|false>
     */
    private array $attributesCache = [];

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly bool $autoValidate = false,
    ) {
    }

    public function onFlush(ManagerEventArgs $event): void
    {
        $manager = $event->getObjectManager();

        $errors = [];
        foreach ($this->getObjectsToValidate($manager) as $object) {
            if ($this->shouldValidate($object) && $error = $this->validate($object)) {
                $errors[] = $error;
            }
        }

        if ($errors) {
            throw new EntityValidationFailedException($errors);
        }
    }

    protected function getObjectsToValidate(ObjectManager $manager): iterable
    {
        $uow = $manager->getUnitOfWork();

        yield from $uow->getScheduledEntityInsertions();
        yield from $uow->getScheduledEntityUpdates();
    }

    private function shouldValidate(object $object): bool
    {
        if (!isset($this->attributesCache[$class = $object::class])) {
            $refClass = new \ReflectionClass($class);
            $refAttribute = $refClass->getAttributes(EnableAutoValidation::class) ?: $refClass->getAttributes(DisableAutoValidation::class);
            $this->attributesCache[$class] = ($refAttribute[0] ?? null)?->newInstance() ?? false;
        }

        return $this->attributesCache[$class] ? $this->attributesCache[$class] instanceof EnableAutoValidation : $this->autoValidate;
    }

    private function validate(object $object): ?ValidationFailedException
    {
        $violations = $this->validator->validate($object);

        return \count($violations) ? new ValidationFailedException($object, $violations) : null;
    }
}
