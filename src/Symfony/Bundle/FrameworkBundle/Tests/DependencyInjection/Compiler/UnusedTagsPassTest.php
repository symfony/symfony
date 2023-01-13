<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class UnusedTagsPassTest extends TestCase
{
    public function testProcess()
    {
        $pass = new UnusedTagsPass();

        $container = new ContainerBuilder();
        $container->register('foo')
            ->addTag('kenrel.event_subscriber');
        $container->register('bar')
            ->addTag('kenrel.event_subscriber');

        $pass->process($container);

        $this->assertSame([sprintf('%s: Tag "kenrel.event_subscriber" was defined on service(s) "foo", "bar", but was never used. Did you mean "kernel.event_subscriber"?', UnusedTagsPass::class)], $container->getCompiler()->getLog());
    }

    public function testMissingKnownTags()
    {
        if (\dirname((new \ReflectionClass(ContainerBuilder::class))->getFileName(), 3) !== \dirname(__DIR__, 5)) {
            $this->markTestSkipped('Tests are not run from the root symfony/symfony metapackage.');
        }

        $this->assertSame(UnusedTagsPassUtils::getDefinedTags(), $this->getKnownTags(), 'The src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Compiler/UnusedTagsPass.php file must be updated; run src/Symfony/Bundle/FrameworkBundle/Resources/bin/check-unused-known-tags.php.');
    }

    private function getKnownTags(): array
    {
        $tags = \Closure::bind(
            static fn () => UnusedTagsPass::KNOWN_TAGS,
            null,
            UnusedTagsPass::class
        )();
        sort($tags);

        return $tags;
    }
}
