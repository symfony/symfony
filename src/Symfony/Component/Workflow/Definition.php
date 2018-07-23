<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Definition
{
    private $places = array();
    private $transitions = array();
    private $initialPlace;
    private $metadataStore;

    /**
     * @param string[]     $places
     * @param Transition[] $transitions
     */
    public function __construct(array $places, array $transitions, string $initialPlace = null, MetadataStoreInterface $metadataStore = null)
    {
        foreach ($places as $place) {
            $this->addPlace($place);
        }

        foreach ($transitions as $transition) {
            $this->addTransition($transition);
        }

        $this->setInitialPlace($initialPlace);

        $this->metadataStore = $metadataStore ?: new InMemoryMetadataStore();
    }

    /**
     * @return string|null
     */
    public function getInitialPlace()
    {
        return $this->initialPlace;
    }

    /**
     * @return string[]
     */
    public function getPlaces(): array
    {
        return $this->places;
    }

    /**
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->metadataStore;
    }

    private function setInitialPlace(string $place = null)
    {
        if (null === $place) {
            return;
        }

        if (!isset($this->places[$place])) {
            throw new LogicException(sprintf('Place "%s" cannot be the initial place as it does not exist.', $place));
        }

        $this->initialPlace = $place;
    }

    private function addPlace(string $place)
    {
        if (!count($this->places)) {
            $this->initialPlace = $place;
        }

        $this->places[$place] = $place;
    }

    private function addTransition(Transition $transition)
    {
        $name = $transition->getName();

        foreach ($transition->getFroms() as $from) {
            if (!isset($this->places[$from])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $from, $name));
            }
        }

        foreach ($transition->getTos() as $to) {
            if (!isset($this->places[$to])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $to, $name));
            }
        }

        $this->transitions[] = $transition;
    }
}
