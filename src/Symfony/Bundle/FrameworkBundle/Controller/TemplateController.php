<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\TwigBundle\Controller\TemplateController as BaseTemplateController;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use the "template" routing option instead.', 'Symfony\Bundle\FrameworkBundle\Controller\TemplateController');

class_alias(BaseTemplateController::class, 'Symfony\Bundle\FrameworkBundle\Controller\TemplateController');

if (false) {
    /**
     * @deprecated since Symfony 8.2, use the "template" routing option instead
     */
    class TemplateController
    {
    }
}
