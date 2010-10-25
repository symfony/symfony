<?php

namespace Symfony\Component\Console\Helper;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
