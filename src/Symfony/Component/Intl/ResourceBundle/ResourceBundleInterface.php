<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle;

/**
 * Gives access to ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ResourceBundleInterface
{
    /**
     * Returns the list of locales that this bundle supports.
     *
     * @return string[] A list of locale codes.
     */
    public function getLocales();
}
