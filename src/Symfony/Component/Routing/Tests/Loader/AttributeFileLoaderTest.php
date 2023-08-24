<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Tests\Fixtures\AttributedClasses\FooClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamAfterCommaController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamAfterParenthesisController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamInlineAfterCommaController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamInlineAfterParenthesisController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamInlineQuotedAfterCommaController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamInlineQuotedAfterParenthesisController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamQuotedAfterCommaController;
use Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures\AttributesClassParamQuotedAfterParenthesisController;
use Symfony\Component\Routing\Tests\Fixtures\OtherAnnotatedClasses\VariadicClass;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class AttributeFileLoaderTest extends TestCase
{
    use ExpectDeprecationTrait;

    private AttributeFileLoader $loader;
    private TraceableAttributeClassLoader $classLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classLoader = new TraceableAttributeClassLoader();
        $this->loader = new AttributeFileLoader(new FileLocator(), $this->classLoader);
    }

    public function testLoad()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributedClasses/FooClass.php'));
        self::assertSame([FooClass::class], $this->classLoader->foundClasses);
    }

    public function testLoadTraitWithClassConstant()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributedClasses/FooTrait.php'));
        self::assertSame([], $this->classLoader->foundClasses);
    }

    public function testLoadFileWithoutStartTag()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Did you forgot to add the "<?php" start tag at the beginning of the file?');
        $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/NoStartTagClass.php');
    }

    public function testLoadVariadic()
    {
        self::assertCount(1, $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/VariadicClass.php'));
        self::assertSame([VariadicClass::class], $this->classLoader->foundClasses);
    }

    /**
     * @group legacy
     */
    public function testLoadAnonymousClass()
    {
        $this->classLoader = new TraceableAttributeClassLoader(new AnnotationReader());
        $this->loader = new AttributeFileLoader(new FileLocator(), $this->classLoader);

        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/OtherAnnotatedClasses/AnonymousClassInTrait.php'));
        self::assertSame([], $this->classLoader->foundClasses);
    }

    public function testLoadAbstractClass()
    {
        self::assertNull($this->loader->load(__DIR__.'/../Fixtures/AttributedClasses/AbstractClass.php'));
        self::assertSame([], $this->classLoader->foundClasses);
    }

    public function testSupports()
    {
        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->assertTrue($this->loader->supports($fixture), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixture, 'attribute'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixture, 'foo'), '->supports() checks the resource type if specified');
    }

    /**
     * @group legacy
     */
    public function testSupportsAnnotations()
    {
        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->expectDeprecation('Since symfony/routing 6.4: The "annotation" route type is deprecated, use the "attribute" route type instead.');
        $this->assertTrue($this->loader->supports($fixture, 'annotation'), '->supports() checks the resource type if specified');
    }

    public function testLoadAttributesClassAfterComma()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamAfterCommaController.php'));
        self::assertSame([AttributesClassParamAfterCommaController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesInlineClassAfterComma()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamInlineAfterCommaController.php'));
        self::assertSame([AttributesClassParamInlineAfterCommaController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesQuotedClassAfterComma()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamQuotedAfterCommaController.php'));
        self::assertSame([AttributesClassParamQuotedAfterCommaController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesInlineQuotedClassAfterComma()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamInlineQuotedAfterCommaController.php'));
        self::assertSame([AttributesClassParamInlineQuotedAfterCommaController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesClassAfterParenthesis()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamAfterParenthesisController.php'));
        self::assertSame([AttributesClassParamAfterParenthesisController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesInlineClassAfterParenthesis()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamInlineAfterParenthesisController.php'));
        self::assertSame([AttributesClassParamInlineAfterParenthesisController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesQuotedClassAfterParenthesis()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamQuotedAfterParenthesisController.php'));
        self::assertSame([AttributesClassParamQuotedAfterParenthesisController::class], $this->classLoader->foundClasses);
    }

    public function testLoadAttributesInlineQuotedClassAfterParenthesis()
    {
        self::assertCount(0, $this->loader->load(__DIR__.'/../Fixtures/AttributesFixtures/AttributesClassParamInlineQuotedAfterParenthesisController.php'));
        self::assertSame([AttributesClassParamInlineQuotedAfterParenthesisController::class], $this->classLoader->foundClasses);
    }
}
