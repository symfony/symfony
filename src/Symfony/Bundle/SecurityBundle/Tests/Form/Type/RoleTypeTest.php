<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Form\Type;

use Symfony\Bundle\SecurityBundle\Form\Type\RoleType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

class RoleTypeTest extends TypeTestCase
{
    protected function getExtensions()
    {
        $roleHierarchy = array(
            'ROLE_SUPER_ADMIN' => array('ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'),
            'ROLE_ADMIN' => array('ROLE_USER'),
        );

        return array(
            new PreloadedExtension(array(new RoleType($roleHierarchy)), array()),
        );
    }

    public function testAllRolesAreShown()
    {
        $form = $this->factory->create(RoleType::class);

        $this->assertCount(4, $form, 'Each role should be shown');
    }
}
