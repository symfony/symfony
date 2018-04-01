<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\MarkingStore;

use Symphony\Component\Workflow\Marking;

/**
 * MarkingStoreInterface is the interface between the Workflow Component and a
 * plain old PHP object: the subject.
 *
 * It converts the Marking into something understandable by the subject and vice
 * versa.
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
