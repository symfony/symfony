<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Base64;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class Base64Test extends TestCase
{
    public function testAllowDataUri()
    {
        $encoding = new Base64(true);

        $this->assertTrue($encoding->requiresDataUri);
    }

    public function testDenyDataUriByDefault()
    {
        $encoding = new Base64();

        $this->assertFalse($encoding->requiresDataUri);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(Base64Dummy::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        $this->assertFalse($aConstraint->requiresDataUri);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        $this->assertTrue($bConstraint->requiresDataUri);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        $this->assertFalse($cConstraint->requiresDataUri);
        $this->assertTrue($cConstraint->urlEncoded);
    }
}

class Base64Dummy
{
    #[Base64]
    private string $a;

    #[Base64(true)]
    private string $b;

    #[Base64(urlEncoded: true)]
    private string $c;
}
