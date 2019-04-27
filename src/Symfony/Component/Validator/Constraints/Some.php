<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 *
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 */
class Some extends AbstractComposite
{
    /**
     * @var string
     *
     * Message for notice Min Violation
     */
    public $minMessage = 'At least {{ limit }} element of this collection should pass validation.|At least {{ limit }} elements or more of this collection should pass validation.';

    /**
     * @var string
     *
     * Message for notice Max Violation
     */
    public $maxMessage = '{{ limit }} or less element of this collection should pass validation.|{{ limit }} or less elements of this collection should pass validation.';

    /**
     * @var int
     *
     * Min number of Success expected
     */
    public $min;

    /**
     * @var int
     *
     * Max number of Success expected
     */
    public $max;

    /**
     * @var int
     *
     * Exactly number of Success expected
     */
    public $exactly;

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
        if ((null !== $this->min || null !== $this->max) && null !== $this->exactly) {
            throw new MissingOptionsException('The "exactly" option cannot be used with "min" or "max" at the same time.', ['min', 'max', 'exactly']);
        }

        if (null === $this->min && null === $this->min) {
            $this->min = 1;
        }

        if (isset($this->max) && ($this->min > $this->max)) {
            throw new MissingOptionsException('The "min" option must not be greater than "max".', ['min', 'max']);
        }
    }
}
