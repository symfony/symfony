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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class MarkingHistoryStore
{
    private $historyProperty;
    private $memoProperty;

    private $historyPropertyAccessor;
    private $memoPropertyAccessor;

    public function __construct(string $historyProperty, string $memoProperty = null, PropertyAccessorInterface $historyPropertyAccessor = null, PropertyAccessorInterface $memoPropertyAccessor = null)
    {
        $this->historyProperty = $historyProperty;
        $this->memoProperty = $memoProperty;

        $this->historyPropertyAccessor = $historyPropertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->memoPropertyAccessor = $memoPropertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param $subject
     * @param $transition Transition
     * @param $marking string
     * @param $workflowName string
     */
    public function updateMarkingHistory($subject, Transition $transition, Marking $marking, $workflowName)
    {
        // get existing state history for this object
        $existingHistory = $this->historyPropertyAccessor->getValue($subject, $this->historyProperty) ?? [];


        // build the array to append to the log, using the workflow name as the log's key
        $arr = array();
        $dt = new \DateTime();
        $arr['timestamp'] = $dt->format('Y-m-d H:i:s');
        $arr['marking'] = $marking->getPlaces();
        $arr['transition'] = $transition->getName();

        if ($this->memoProperty !== null) {
            $arr['memo'] = $this->memoPropertyAccessor->getValue($subject, $this->memoProperty) ?? '';  // an optional memo
        }

        // the key is used to allow logging the history of multiple workflows
        $key = $workflowName;
        if (!array_key_exists($key, $existingHistory)) {
            $existingHistory[$key] = array();
        }
        $existingHistory[$key][] = $arr;

        // set the history property value
        $this->historyPropertyAccessor->setValue($subject, $this->historyProperty, $existingHistory);

        // finally, clear out the memo field now that it's been logged
        $this->memoPropertyAccessor->setValue($subject, $this->memoProperty, null);
    }
}
