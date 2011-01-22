<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TemplateLocatorInterface
{
    function locate($name);
}
