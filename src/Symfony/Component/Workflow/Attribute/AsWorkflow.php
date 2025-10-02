<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

use Symfony\Component\Workflow\WorkflowType;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsWorkflow
{
    /**
     * @param string-class|string[]|array<int, array{name: string, metadata: array}> $places
     */
    public function __construct(
        public string $name,
        public string|array $places = [],
        public WorkflowType $type = WorkflowType::StateMachine,
        public array $supports = [],
        public array $markingStore = [],
        public array $metadata = [],
        public bool $auditTrail = true,
    ) {
        if (\is_string($this->places)) {
            if (!enum_exists($this->places)) {
                throw new \InvalidArgumentException(\sprintf('The "places" attribute of the "%s" workflow must be an array or a valid enum name, "%s" given.', self::class, $this->places));
            }
            if (!is_a($this->places, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('The "places" attribute of the "%s" workflow must be a backed enum', self::class));
            }
            $r = new \ReflectionEnum($this->places);

            $this->places = [];
            foreach ($r->getCases() as $case) {
                $placeDescription = ($case->getAttributes(Place::class)[0] ?? null)?->newInstance();

                $this->places[] = [
                    'name' => $case->getValue()->value,
                    'metadata' => $placeDescription?->metadata ?? [],
                ];
            }

            return;
        }

        foreach ($this->places as $k => $place) {
            if (\is_string($place)) {
                $this->places[$k] = [
                    'name' => $place,
                    'metadata' => [],
                ];
            }
        }
    }
}
