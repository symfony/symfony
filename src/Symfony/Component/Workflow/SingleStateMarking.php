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

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * SingleStateMarking contains the place of one token.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jules Pietri <jules@heahprod.com>
 */
class SingleStateMarking extends Marking
{
    /**
     * @param mixed $place A scalar as only place or null
     */
    public function __construct($place = null)
    {
        if (null !== $place && !is_scalar($place)) {
            throw new InvalidArgumentException(sprintf('"%s" instances only accept scalar or null as single place, but got "%s".', __CLASS__, is_object($place) ? get_class($place) : gettype($place)));
        }

        if ($place) {
            $this->mark($place);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mark($place)
    {
        $this->places = array($place => 1);
    }

    /**
     * {@inheritdoc}
     */
    public function unmark($place)
    {
        unset($this->places[$place]);
    }
}
