<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Extractor\PrivateReflectionExtractor;
use Symfony\Component\AutoMapper\Tests\Fixtures;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class PrivateReflectionExtractorTest extends TestCase
{
    /**
     * @var PrivateReflectionExtractor
     */
    protected $privateReflectionExtractor;

    public function setUp(): void
    {
        $this->privateReflectionExtractor = new PrivateReflectionExtractor();
    }

    public function testProperties(): void
    {
        $userReflection = new \ReflectionClass(Fixtures\User::class);
        $properties = $this->privateReflectionExtractor->getProperties(Fixtures\User::class);

        foreach ($userReflection->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED) as $reflectionProperty) {
            self::assertTrue(in_array($reflectionProperty->getName(), $properties));
        }
    }
}
