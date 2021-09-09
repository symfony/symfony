<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * A form element whose errors can be cleared.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 */
interface ClearableErrorsInterface
{
    /**
     * Removes all the errors of this form.
     *
     * @param bool $deep Whether to remove errors from child forms as well
     *
     * @return $this
     */
    public function clearErrors(bool $deep = false);
}
