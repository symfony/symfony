<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Group;

/**
 * Returns the group that is currently being validated.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface GroupManagerInterface
{
    /**
     * Returns the group that is currently being validated.
     *
     * @return string|null The current group or null, if no validation is
     *                     active.
     */
    public function getCurrentGroup();
}
