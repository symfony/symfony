<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\MarkingStore;

use Symfony\Component\Workflow\Marking;

/**
 * MarkingStoreInterface.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface MarkingStoreInterface
{
    /**
     * Gets a Marking from a subject.
     *
     * @param object $subject A subject
     *
     * @return Marking The marking
     */
    public function getMarking($subject);

    /**
     * Sets a Marking to a subject.
     *
     * @param object  $subject A subject
     * @param Marking $marking A marking
     */
    public function setMarking($subject, Marking $marking);
}
