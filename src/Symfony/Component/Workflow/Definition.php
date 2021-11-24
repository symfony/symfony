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
use Symfony\Component\Workflow\Utils\PlaceEnumerationUtils;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Definition
{
    private array $places = [];
    private array $transitions = [];
    private array $initialPlaces = [];
    private MetadataStoreInterface $metadataStore;

    /**
     * @param string[]|\UnitEnum[]                       $places
     * @param Transition[]                               $transitions
     * @param string|string[]|\UnitEnum|\UnitEnum[]|null $initialPlaces
     */
    public function __construct(array $places, array $transitions, string|\UnitEnum|array $initialPlaces = null, MetadataStoreInterface $metadataStore = null)
    {
        foreach ($places as $place) {
            $this->addPlace($place);
        }

        foreach ($transitions as $transition) {
            $this->addTransition($transition);
        }

        $this->setInitialPlaces($initialPlaces);

        $this->metadataStore = $metadataStore ?? new InMemoryMetadataStore();
    }

    /**
     * @return string[]
     */
    public function getInitialPlaces(): array
    {
        return array_map(static fn ($element) => PlaceEnumerationUtils::getTypedValue($element), $this->initialPlaces);
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

    private function setInitialPlaces(string|\UnitEnum|array $places = null)
    {
        if (!$places) {
            return;
        }

        $places = array_map(static fn ($element) => PlaceEnumerationUtils::getPlaceKey($element), \is_array($places) ? $places : [$places]);

        foreach ($places as $place) {
            if (!isset($this->places[$place])) {
                throw new LogicException(sprintf('Place "%s" cannot be the initial place as it does not exist.', PlaceEnumerationUtils::getPlaceKey($place)));
            }
        }

        $this->initialPlaces = $places;
    }

    private function addPlace(string|\UnitEnum $place)
    {
        if (!\count($this->places)) {
            $this->initialPlaces = [PlaceEnumerationUtils::getPlaceKey($place)];
        }

        $this->places[PlaceEnumerationUtils::getPlaceKey($place)] = $place;
    }

    private function addTransition(Transition $transition)
    {
        $name = $transition->getName();

        foreach ($transition->getFroms() as $from) {
            $from = PlaceEnumerationUtils::getPlaceKey($from);
            if (!isset($this->places[$from])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $from, $name));
            }
        }

        foreach ($transition->getTos() as $to) {
            $to = PlaceEnumerationUtils::getPlaceKey($to);
            if (!isset($this->places[$to])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $to, $name));
            }
        }

        $this->transitions[] = $transition;
    }
}
