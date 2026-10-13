<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\EmojiExtension;
use Symfony\Bridge\Twig\Extension\ExpressionExtension;
use Symfony\Bridge\Twig\Extension\HtmlSanitizerExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Bridge\Twig\Extension\WorkflowExtension;
use Symfony\Bridge\Twig\Extension\YamlExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Workflow\Registry;
use Twig\Extension\AttributeExtension;
use Twig\Node\Nodes;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class AttributeDeclarationsTest extends TestCase
{
    #[DataProvider('provideExtensions')]
    public function testAttributesDeclareTheSameCallablesAsTheExtension(string $class)
    {
        if (EmojiExtension::class === $class && !\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }

        $extension = match ($class) {
            AssetExtension::class => new AssetExtension(new Packages()),
            EmojiExtension::class => new EmojiExtension(),
            ExpressionExtension::class => new ExpressionExtension(),
            HtmlSanitizerExtension::class => new HtmlSanitizerExtension(new ServiceLocator([])),
            HttpFoundationExtension::class => new HttpFoundationExtension(new UrlHelper(new RequestStack())),
            LogoutUrlExtension::class => new LogoutUrlExtension(new LogoutUrlGenerator()),
            RoutingExtension::class => new RoutingExtension($this->createStub(UrlGeneratorInterface::class)),
            SecurityExtension::class => new SecurityExtension($this->createStubForIntersectionOfInterfaces([AuthorizationCheckerInterface::class, UserAuthorizationCheckerInterface::class])),
            WebLinkExtension::class => new WebLinkExtension(new RequestStack()),
            WorkflowExtension::class => new WorkflowExtension(new Registry()),
            YamlExtension::class => new YamlExtension(),
        };
        $attributes = new AttributeExtension($class);

        $this->assertNotSame([], $attributes->getFunctions() ?: $attributes->getFilters());
        $this->assertSame(self::describe($extension->getFunctions()), self::describe($attributes->getFunctions()));
        $this->assertSame(self::describe($extension->getFilters()), self::describe($attributes->getFilters()));
        $this->assertSame(self::describe($extension->getTests()), self::describe($attributes->getTests()));
    }

    public static function provideExtensions(): iterable
    {
        yield [AssetExtension::class];
        yield [EmojiExtension::class];
        yield [ExpressionExtension::class];
        yield [HtmlSanitizerExtension::class];
        yield [HttpFoundationExtension::class];
        yield [LogoutUrlExtension::class];
        yield [RoutingExtension::class];
        yield [SecurityExtension::class];
        yield [WebLinkExtension::class];
        yield [WorkflowExtension::class];
        yield [YamlExtension::class];
    }

    public function testEveryExtensionWithAttributesIsChecked()
    {
        $checked = array_merge(...iterator_to_array(self::provideExtensions(), false));

        foreach (glob(\dirname(__DIR__, 2).'/Extension/*Extension.php') as $file) {
            $class = 'Symfony\Bridge\Twig\Extension\\'.basename($file, '.php');

            $attributes = new AttributeExtension($class);

            if ($attributes->getFunctions() || $attributes->getFilters() || $attributes->getTests()) {
                $this->assertContains($class, $checked);
            }
        }
    }

    /**
     * @param list<TwigFunction|TwigFilter|TwigTest> $callables
     */
    private static function describe(array $callables): array
    {
        $descriptions = [];

        foreach ($callables as $callable) {
            $method = $callable->getCallable();
            $descriptions[$callable->getName()] = [
                'method' => $method instanceof \Closure ? (new \ReflectionFunction($method))->name : $method[1],
                'safe' => $callable instanceof TwigTest ? null : $callable->getSafe(new Nodes()),
                'needs_charset' => $callable->needsCharset(),
                'needs_environment' => $callable->needsEnvironment(),
                'needs_context' => $callable->needsContext(),
                'is_variadic' => $callable->isVariadic(),
                'is_deprecated' => $callable->isDeprecated(),
                'pre_escape' => $callable instanceof TwigFilter ? $callable->getPreEscape() : null,
                'preserves_safety' => $callable instanceof TwigFilter ? $callable->getPreservesSafety() : null,
            ];
        }
        ksort($descriptions);

        return $descriptions;
    }
}
