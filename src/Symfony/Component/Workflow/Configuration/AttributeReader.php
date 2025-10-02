<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Configuration;

use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\WorkflowType;

/**
 * @internal
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AttributeReader
{
    public function extractConfiguration(AsWorkflow $attribute, \ReflectionClass $reflection): array
    {
        return [
            'name' => $attribute->name,
            'type' => WorkflowType::StateMachine === $attribute->type ? 'state_machine' : 'workflow',
            'marking_store' => $attribute->markingStore ?: ['type' => 'method', 'property' => 'marking'],
            'supports' => $attribute->supports,
            'places' => $attribute->places,
            'transitions' => $this->extractTransitions($reflection),
            'metadata' => $attribute->metadata,
            'audit_trail' => [
                'enabled' => $attribute->auditTrail,
            ],
            'initial_marking' => [],
            'events_to_dispatch' => null,
        ];
    }

    private function extractTransitions(\ReflectionClass $reflection): array
    {
        $transitions = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            $transitionAttributes = $constant->getAttributes(Transition::class);

            if (!$transitionAttributes) {
                continue;
            }

            foreach ($transitionAttributes as $attribute) {
                $attribute = $attribute->newInstance();

                $transition = [
                    'name' => $constant->getValue(),
                    'from' => $attribute->froms,
                    'to' => $attribute->tos,
                    'metadata' => $attribute->metadata,
                ];

                if ($attribute->guard) {
                    $transition['guard'] = $attribute->guard;
                }

                $transitions[] = $transition;
            }
        }

        return $transitions;
    }
}
