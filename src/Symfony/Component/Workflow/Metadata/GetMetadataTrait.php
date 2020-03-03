<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Metadata;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\Transition;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
trait GetMetadataTrait
{
    public function getMetadata(string $key, $subject = null)
    {
        if (null === $subject) {
            return $this->getWorkflowMetadata()[$key] ?? null;
        }

        if (\is_string($subject)) {
            $metadataBag = $this->getPlaceMetadata($subject);
            if (!$metadataBag) {
                return null;
            }

            return $metadataBag[$key] ?? null;
        }

        if ($subject instanceof Transition) {
            $metadataBag = $this->getTransitionMetadata($subject);
            if (!$metadataBag) {
                return null;
            }

            return $metadataBag[$key] ?? null;
        }

        throw new InvalidArgumentException(sprintf('Could not find a MetadataBag for the subject of type "%s".', get_debug_type($subject)));
    }
}
