<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Security;

/**
 * @group legacy
 */
class LegacySecurityContextInterfaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test if the BC Layer is working as intended.
     */
    public function testConstantSync()
    {
        $this->assertSame(Security::ACCESS_DENIED_ERROR, SecurityContextInterface::ACCESS_DENIED_ERROR);
        $this->assertSame(Security::AUTHENTICATION_ERROR, SecurityContextInterface::AUTHENTICATION_ERROR);
        $this->assertSame(Security::LAST_USERNAME, SecurityContextInterface::LAST_USERNAME);
    }
}
