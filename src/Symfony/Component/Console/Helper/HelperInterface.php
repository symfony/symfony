<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

@trigger_error('The '.__NAMESPACE__.'\HelperInterface class is deprecated since version 4.2.', E_USER_DEPRECATED);

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 4.2
 */
interface HelperInterface
{
    /**
     * Sets the helper set associated with this helper.
     *
     * @deprecated since version 4.2
     */
    public function setHelperSet(HelperSet $helperSet = null);

    /**
     * Gets the helper set associated with this helper.
     *
     * @return HelperSet A HelperSet instance
     *
     * @deprecated since version 4.2
     */
    public function getHelperSet();

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     *
     * @deprecated since version 4.2
     */
    public function getName();
}
