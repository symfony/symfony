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

/**
 * @requires PHP 7.0
 */
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

    private function getKnownTags()
    {
        // get tags in UnusedTagsPass
        $target = \dirname(__DIR__, 3).'/DependencyInjection/Compiler/UnusedTagsPass.php';
        $contents = file_get_contents($target);
        preg_match('{private \$knownTags = \[(.+?)\];}sm', $contents, $matches);
        $tags = array_values(array_filter(array_map(function ($str) {
            return trim(preg_replace('{^ +\'(.+)\',}', '$1', $str));
        }, explode("\n", $matches[1]))));
        sort($tags);

        return $tags;
    }
}
