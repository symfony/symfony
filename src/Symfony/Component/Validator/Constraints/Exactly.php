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
class Exactly extends AbstractComposite
{
    /**
     * @var string
     *
     * Message for notice Exactly Violation
     */
    public $exactlyMessage = 'Exactly {{ limit }} element of this collection should pass validation.|Exactly {{ limit }} elements of this collection should pass validation.';

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
        if (null === $this->exactly) {
            throw new MissingOptionsException('The "exactly" option cannot be null', ['exactly']);
        }
    }
}
