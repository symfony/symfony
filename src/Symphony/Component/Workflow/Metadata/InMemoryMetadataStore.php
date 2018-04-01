<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Metadata;

use Symphony\Component\Workflow\Transition;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class InMemoryMetadataStore implements MetadataStoreInterface
{
    use GetMetadataTrait;

    private $workflowMetadata;
    private $placesMetadata;
    private $transitionsMetadata;

    public function __construct(array $workflowMetadata = array(), array $placesMetadata = array(), \SplObjectStorage $transitionsMetadata = null)
    {
        $this->workflowMetadata = $workflowMetadata;
        $this->placesMetadata = $placesMetadata;
        $this->transitionsMetadata = $transitionsMetadata ?: new \SplObjectStorage();
    }

    public function getWorkflowMetadata(): array
    {
        return $this->workflowMetadata;
    }

    public function getPlaceMetadata(string $place): array
    {
        return $this->placesMetadata[$place] ?? array();
    }

    public function getTransitionMetadata(Transition $transition): array
    {
        return $this->transitionsMetadata[$transition] ?? array();
    }
}
