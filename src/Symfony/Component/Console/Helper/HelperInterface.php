<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface HelperInterface
{
    /**
     * Sets the helper set associated with this helper.
     *
     * @param HelperSet $helperSet A HelperSet instance
     */
    function setHelperSet(HelperSet $helperSet = null);

    /**
     * Gets the helper set associated with this helper.
     *
     * @return HelperSet A HelperSet instance
     */
    function getHelperSet();

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    function getName();
}
