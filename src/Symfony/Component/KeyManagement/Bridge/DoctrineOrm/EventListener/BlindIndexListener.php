<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Psr\Container\ContainerInterface;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

/**
 * Fills the properties marked with {@see BlindIndexed} from the ones they index.
 *
 * It runs on `onFlush` rather than on `prePersist` and `preUpdate`, for one reason that matters:
 * `prePersist` is dispatched when `persist()` is called, not when the flush happens, so an
 * application that persists an entity before filling it would have written an empty tag and lost
 * the row for every search. `onFlush` sees the values the INSERT is about to carry, whatever the
 * order the application did things in, and both cases then take the same path.
 *
 * A tag is derived on every flush of an entity that carries one, not only when the value changed:
 * it costs a keyed digest, the index key is unwrapped once per process, and a row whose tag was
 * written before the index existed repairs itself the next time it is saved. The change set is
 * only recomputed when the tag actually differs from what the property held.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class BlindIndexListener
{
    /**
     * @var array<class-string, list<array{\ReflectionProperty, \ReflectionProperty, class-string<BlindIndex>}>>
     */
    private array $indexed = [];

    /**
     * @param ContainerInterface $indexes The blind indexes of the application, keyed by class name
     */
    public function __construct(
        private readonly ContainerInterface $indexes,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $unitOfWork = $manager->getUnitOfWork();

        foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates()] as $entities) {
            foreach ($entities as $entity) {
                if ($this->fill($entity)) {
                    $unitOfWork->recomputeSingleEntityChangeSet($manager->getClassMetadata($entity::class), $entity);
                }
            }
        }
    }

    /**
     * @return bool Whether any tag was written, and the change set therefore has to be recomputed
     */
    private function fill(object $entity): bool
    {
        $written = false;

        foreach ($this->indexedProperties($entity::class) as [$source, $target, $index]) {
            if (!$source->isInitialized($entity)) {
                continue;
            }

            $value = $source->getValue($entity);
            if (null !== $value && !\is_string($value) && !$value instanceof \Stringable) {
                throw new \LogicException(\sprintf('The property "%s::$%s" holds a value of type "%s", which "%s" cannot index: a blind index is derived from a string.', $entity::class, $source->name, get_debug_type($value), BlindIndexed::class));
            }

            $tag = null === $value ? null : $this->indexes->get($index)->of((string) $value);

            if (!$target->isInitialized($entity) || $target->getValue($entity) !== $tag) {
                $target->setValue($entity, $tag);
                $written = true;
            }
        }

        return $written;
    }

    /**
     * The reflection is resolved once per class, since a flush walks every entity it holds and most
     * of them carry no index at all.
     *
     * @param class-string $class
     *
     * @return list<array{\ReflectionProperty, \ReflectionProperty, class-string<BlindIndex>}>
     */
    private function indexedProperties(string $class): array
    {
        if (isset($this->indexed[$class])) {
            return $this->indexed[$class];
        }

        $properties = [];
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                $properties[$property->name] ??= $property;
            }
        }

        $indexed = [];
        foreach ($properties as $target) {
            foreach ($target->getAttributes(BlindIndexed::class) as $attribute) {
                $attribute = $attribute->newInstance();

                if (!isset($properties[$attribute->property])) {
                    throw new \LogicException(\sprintf('The property "%s::$%s" is indexed by "%s::$%s", but no such property is declared on that entity.', $class, $attribute->property, $class, $target->name));
                }

                if (!$this->indexes->has($attribute->index)) {
                    throw new \LogicException(\sprintf('No blind index of class "%s" is registered, as "%s::$%s" requires. Register it as a service, or check the class the attribute names.', $attribute->index, $class, $target->name));
                }

                $indexed[] = [$properties[$attribute->property], $target, $attribute->index];
            }
        }

        return $this->indexed[$class] = $indexed;
    }
}
