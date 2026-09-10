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

use Symfony\Component\Routing\Controller\RedirectController as BaseRedirectController;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController', BaseRedirectController::class);

class_alias(BaseRedirectController::class, 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController');
