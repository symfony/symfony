<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle;

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller\LocalizedController;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Controller\LoginController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FormLoginBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->register(LoginController::class)
            ->setPublic(true)
            ->addTag('container.service_subscriber');

        $container
            ->register(LocalizedController::class)
            ->setPublic(true)
            ->addTag('container.service_subscriber');
    }
}
