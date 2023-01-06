<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AuthenticatorBundle;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfileController extends AbstractController
{
    public function __invoke()
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json(['email' => $this->getUser()->getUserIdentifier()]);
    }
}
