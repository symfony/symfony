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

use Symfony\Component\Workflow\Transition;

/**
 * MetadataStoreInterface is able to fetch metadata for a specific workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface MetadataStoreInterface
{
    public function getWorkflowMetadata(): array;

    public function getPlaceMetadata(string $place): array;

    public function getTransitionMetadata(Transition $transition): array;

    /**
     * Returns the metadata for a specific subject.
     *
     * This is a proxy method.
     *
     * @param string|Transition|null $subject Use null to get workflow metadata
     *                                        Use a string (the place name) to get place metadata
     *                                        Use a Transition instance to get transition metadata
     *
     * @return mixed
     */
    public function getMetadata(string $key, string|Transition|null $subject = null);
}
