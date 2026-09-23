<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\EntryTypeProviderInterface;
use Symfony\Component\Form\Event\PostSetDataEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResizeFormListener implements EventSubscriberInterface
{
    protected array $prototypeOptions;

    private \Closure|bool $deleteEmpty;
    private ?\Closure $entryName;
    private array $preSetDataChildrenStack = [];

    /**
     * @param string|array<string, string>               $type              A type, or one type per entry name when $entryTypeProvider is given
     * @param array<mixed>                               $options           Options for $type, or one set of options per entry name
     * @param array<mixed>|null                          $prototypeOptions  Same shape as $options
     * @param EntryTypeProviderInterface|null            $entryTypeProvider Picks the entry name to use for a given entry's data
     * @param string|PropertyPathInterface|callable|null $entryName         Names the form of each entry from the entry itself instead of its key: a property path read from the entry, or a callable taking the entry and its key
     */
    public function __construct(
        private string|array $type,
        private array $options = [],
        private bool $allowAdd = false,
        private bool $allowDelete = false,
        bool|callable $deleteEmpty = false,
        ?array $prototypeOptions = null,
        private bool $keepAsList = false,
        private ?EntryTypeProviderInterface $entryTypeProvider = null,
        string|PropertyPathInterface|callable|null $entryName = null,
    ) {
        $this->deleteEmpty = \is_bool($deleteEmpty) ? $deleteEmpty : $deleteEmpty(...);
        $this->prototypeOptions = $prototypeOptions ?? $options;

        if (\is_string($entryName) || $entryName instanceof PropertyPathInterface) {
            $propertyPath = \is_string($entryName) ? new PropertyPath($entryName) : $entryName;
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $entryName = static fn (mixed $entry) => $propertyAccessor->getValue($entry, $propertyPath);
        }

        $this->entryName = null === $entryName ? null : $entryName(...);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::POST_SET_DATA => ['postSetData', 255], // as early as possible
            FormEvents::PRE_SUBMIT => 'preSubmit',
            // (MergeCollectionListener, MergeDoctrineCollectionListener)
            FormEvents::SUBMIT => ['onSubmit', 50],
        ];
    }

    final public function preSetData(PreSetDataEvent $event): void
    {
        $this->preSetDataChildrenStack[] = iterator_to_array($event->getForm());
    }

    final public function postSetData(PostSetDataEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData() ?? [];
        $childrenToRemove = array_pop($this->preSetDataChildrenStack);

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if (null === $childrenToRemove) {
            // First remove all rows
            foreach ($form as $name => $child) {
                $form->remove($name);
            }
        } else {
            // First remove all rows that existed before PRE_SET_DATA listeners were called
            foreach ($childrenToRemove as $name => $child) {
                if ($form->has($name) && $form->get($name) === $child) {
                    $form->remove($name);
                }
            }
        }

        // Then add all rows again in the correct order
        $names = [];
        foreach ($data as $key => $value) {
            $name = $key;

            if (null !== $this->entryName) {
                $name = $this->nameOf($value, $key);

                if (isset($names[$name])) {
                    throw new RuntimeException(\sprintf('The "entry_name" option must return a distinct name for each entry, but it returned "%s" for the entries at keys "%s" and "%s".', $name, $names[$name], $key));
                }

                $names[$name] = $key;
            }

            if ($form->has($name)) {
                continue;
            }

            $form->add($name, $this->forModelData($this->type, $value), $this->withPropertyPath($this->forModelData($this->options, $value), $key));
        }
    }

    public function preSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (!\is_array($data)) {
            $data = [];
        }

        // Remove all empty rows
        if ($this->allowDelete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name])) {
                    $form->remove($name);
                }
            }
        }

        // Add all additional rows
        if ($this->allowAdd) {
            $usedKeys = null;
            foreach ($data as $name => $value) {
                if ($form->has($name)) {
                    continue;
                }

                if (null === $this->entryName) {
                    $key = $name;
                } else {
                    // the name comes from the client, the entry is appended after the existing ones
                    $usedKeys ??= $this->usedKeys($form);
                    $key = \count($usedKeys);
                    while (isset($usedKeys[$key])) {
                        ++$key;
                    }
                    $usedKeys[$key] = true;
                }

                $form->add($name, $this->forSubmittedData($this->type, $value), $this->withPropertyPath($this->forSubmittedData($this->prototypeOptions, $value), $key));
            }
        }
    }

    public function onSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData() ?? [];

        // At this point, $data is an array or an array-like object that already contains the
        // new entries, which were added by the data mapper. The data mapper ignores existing
        // entries, so we need to manually unset removed entries in the collection.

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if ($this->deleteEmpty) {
            $previousData = $form->getData();
            /** @var FormInterface $child */
            foreach ($form as $name => $child) {
                if (!$child->isValid() || !$child->isSynchronized()) {
                    continue;
                }

                $key = $this->keyOf($child);
                $isNew = !isset($previousData[$key]);
                $isEmpty = \is_callable($this->deleteEmpty) ? ($this->deleteEmpty)($child->getData()) : $child->isEmpty();

                // $isNew can only be true if allowAdd is true, so we don't
                // need to check allowAdd again
                if ($isEmpty && ($isNew || $this->allowDelete)) {
                    unset($data[$key]);
                    $form->remove($name);
                }
            }
        }

        // The data mapper only adds, but does not remove items, so do this
        // here
        if ($this->allowDelete) {
            $keptKeys = $toDelete = [];

            foreach ($form as $child) {
                $keptKeys[$this->keyOf($child)] = true;
            }

            foreach ($data as $key => $child) {
                if (!isset($keptKeys[$key])) {
                    $toDelete[] = $key;
                }
            }

            foreach ($toDelete as $key) {
                unset($data[$key]);
            }
        }

        if ($this->keepAsList) {
            $formReindex = $dataKeys = [];
            foreach ($data as $key => $value) {
                $dataKeys[] = $key;
            }
            foreach ($dataKeys as $key) {
                unset($data[$key]);
            }
            foreach ($form as $name => $child) {
                $formReindex[] = $child;
                $form->remove($name);
            }
            foreach ($formReindex as $index => $child) {
                $form->add(null === $this->entryName ? $index : $child->getName(), $this->forModelData($this->type, $child->getData()), array_replace(
                    $this->withPropertyPath($this->forModelData($this->options, $child->getData()), $index),
                    ['data' => $child->getData()],
                ));
                $data[$index] = $child->getData();
            }
        }

        $event->setData($data);
    }

    private function forModelData(string|array $value, mixed $data): string|array
    {
        return \is_string($this->type) ? $value : $this->entryOf($value, $this->entryTypeProvider->forModelData($data));
    }

    private function forSubmittedData(string|array $value, mixed $data): string|array
    {
        return \is_string($this->type) ? $value : $this->entryOf($value, $this->entryTypeProvider->forSubmittedData($data));
    }

    private function entryOf(array $value, int|string $name): string|array
    {
        if (!\array_key_exists($name, $this->type)) {
            throw new InvalidOptionsException(\sprintf('The "%s" instance given as "entry_type_provider" must return a key of the "entry_types" option, but it returned "%s". Allowed keys are "%s".', $this->entryTypeProvider::class, $name, implode('", "', array_keys($this->type))));
        }

        return $value[$name];
    }

    private function withPropertyPath(array $options, int|string $key): array
    {
        $propertyPath = ['property_path' => '['.$key.']'];

        // an entry form named after its entry finds the entry through this path only
        return null === $this->entryName ? array_replace($propertyPath, $options) : array_replace($options, $propertyPath);
    }

    private function nameOf(mixed $entry, int|string $key): int|string
    {
        $name = ($this->entryName)($entry, $key);

        if ($name instanceof \Stringable) {
            $name = (string) $name;
        }

        if (!\is_int($name) && !\is_string($name)) {
            throw new RuntimeException(\sprintf('The "entry_name" option must return an int or a string for each entry, but it returned "%s" for the entry at key "%s".', get_debug_type($name), $key));
        }

        return $name;
    }

    /**
     * Returns the key of the entry that a child form is mapped to.
     */
    private function keyOf(FormInterface $child): string
    {
        if (null === $this->entryName) {
            return $child->getName();
        }

        $propertyPath = $child->getPropertyPath();

        return $propertyPath->getElement($propertyPath->getLength() - 1);
    }

    /**
     * @return array<int|string, true>
     */
    private function usedKeys(FormInterface $form): array
    {
        $keys = [];

        foreach ($form->getData() ?? [] as $key => $value) {
            $keys[$key] = true;
        }

        foreach ($form as $child) {
            $keys[$this->keyOf($child)] = true;
        }

        return $keys;
    }
}
