<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests\Normalizer;

use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use Prophecy\Argument;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Normalizer\NormalizerGenerator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;

class NormalizerGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Standard */
    protected $printer;

    public function setUp()
    {
        $this->printer = new Standard();
    }

    public function testGenerateDummyNormalizer()
    {
        $normalizerStatementsGenerator = $this->prophesize(AstGeneratorInterface::class);
        $normalizerStatementsGenerator->supportsGeneration(Dummy::class)->willReturn(true);
        $normalizerStatementsGenerator->generate(Dummy::class, Argument::type('array'))->willReturn([
            new Stmt\Return_(new Expr\New_(new Name("\\stdClass")))
        ]);

        $denormalizerStatementsGenerator = $this->prophesize(AstGeneratorInterface::class);
        $denormalizerStatementsGenerator->supportsGeneration(Dummy::class)->willReturn(true);
        $denormalizerStatementsGenerator->generate(Dummy::class, Argument::type('array'))->willReturn([
            new Stmt\Return_(new Expr\New_(new Name('\\'. Dummy::class)))
        ]);

        $normalizerGenerator = new NormalizerGenerator($normalizerStatementsGenerator->reveal(), $denormalizerStatementsGenerator->reveal());

        $this->assertTrue($normalizerGenerator->supportsGeneration(Dummy::class));

        eval($this->printer->prettyPrint($normalizerGenerator->generate(Dummy::class)));

        $this->assertTrue(class_exists('DummyNormalizer'));

        $dummyNormalizer = new \DummyNormalizer();

        $this->assertInstanceOf(NormalizerInterface::class, $dummyNormalizer);
        $this->assertInstanceOf(DenormalizerInterface::class, $dummyNormalizer);
        $this->assertInstanceOf(SerializerAwareInterface::class, $dummyNormalizer);

        $this->assertTrue($dummyNormalizer->supportsNormalization(new Dummy()));
        $this->assertTrue($dummyNormalizer->supportsDenormalization([], Dummy::class));

        $normalized = $dummyNormalizer->normalize(new Dummy());
        $denormalized = $dummyNormalizer->denormalize(new \stdClass(), Dummy::class);

        $this->assertInstanceOf(\stdClass::class, $normalized);
        $this->assertInstanceOf(Dummy::class, $denormalized);
    }
}

class Dummy
{
}
