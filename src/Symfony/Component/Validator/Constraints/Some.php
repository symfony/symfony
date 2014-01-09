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

use Symfony\Component\Validator\Constraints\AbstractComposite;
use Symfony\Component\Validator\Exception\MissingOptionsException;


/**
 * @Annotation
 *
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
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
    public $maxMessage = '{{ limit }} or less element of this collection should pass validation.';

    /**
     * @var string
     *
     * Message for notice Exactly Violation
     */
    public $exactlyMessage = 'Exactly {{ limit }} element of this collection should pass validation.|Exactly {{ limit }} elements of this collection should pass validation';

    /**
     * @var int
     *
     * Min number of Succeds expected
     */
    public $min;

    /**
     * @var int
     *
     * Max number of Succeds expected
     */
    public $max;

    /**
     * @var int
     *
     * Exactly number of Succeds expected
     */
    public $exactly;

    /**
     * {@inheritDoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if ( (isset($this->min) || isset($this->max)) && isset($this->exactly)) {
            throw new MissingOptionsException(sprintf('"min" or "max" and "exactly" must not be given at the same time: %s', __CLASS__), array('min', 'max', 'exactly'));
        }

        if (!isset($this->min) && !isset($this->exactly)){
            $this->min = 1;
        }

        if ( isset($this->max) && ($this->min > $this->max)) {
            throw new MissingOptionsException(sprintf('"min" must not be given great than "max": %s', __CLASS__), array('min', 'max'));
        }
    }
}
