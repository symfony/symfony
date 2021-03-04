<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\LegacyBundle\Entity\LegacyPerson;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ModernBundle\src\Entity\ModernPerson;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class BundlePathsTest extends AbstractWebTestCase
{
    public function testBundlePublicDir()
    {
        $kernel = static::bootKernel(['test_case' => 'BundlePaths']);
        $projectDir = sys_get_temp_dir().'/'.uniqid('sf_bundle_paths', true);

        $fs = new Filesystem();
        $fs->mkdir($projectDir.'/public');
        $command = (new Application($kernel))->add(new AssetsInstallCommand($fs, $projectDir));
        $exitCode = (new CommandTester($command))->execute(['target' => $projectDir.'/public']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($projectDir.'/public/bundles/modern/modern.css');
        $this->assertFileExists($projectDir.'/public/bundles/legacy/legacy.css');

        $fs->remove($projectDir);
    }

    public function testBundleTwigTemplatesDir()
    {
        static::bootKernel(['test_case' => 'BundlePaths']);
        $twig = static::getContainer()->get('twig.alias');
        $bundlesMetadata = static::getContainer()->getParameter('kernel.bundles_metadata');

        $this->assertSame([$bundlesMetadata['LegacyBundle']['path'].'/Resources/views'], $twig->getLoader()->getPaths('Legacy'));
        $this->assertSame("OK\n", $twig->render('@Legacy/index.html.twig'));

        $this->assertSame([$bundlesMetadata['ModernBundle']['path'].'/templates'], $twig->getLoader()->getPaths('Modern'));
        $this->assertSame("OK\n", $twig->render('@Modern/index.html.twig'));
    }

    public function testBundleTranslationsDir()
    {
        static::bootKernel(['test_case' => 'BundlePaths']);
        $translator = static::getContainer()->get('translator.alias');

        $this->assertSame('OK', $translator->trans('ok_label', [], 'legacy'));
        $this->assertSame('OK', $translator->trans('ok_label', [], 'modern'));
    }

    public function testBundleValidationConfigDir()
    {
        static::bootKernel(['test_case' => 'BundlePaths']);
        $validator = static::getContainer()->get('validator.alias');

        $this->assertTrue($validator->hasMetadataFor(LegacyPerson::class));
        $this->assertCount(1, $constraintViolationList = $validator->validate(new LegacyPerson('john', 5)));
        $this->assertSame('This value should be greater than 18.', $constraintViolationList->get(0)->getMessage());

        $this->assertTrue($validator->hasMetadataFor(ModernPerson::class));
        $this->assertCount(1, $constraintViolationList = $validator->validate(new ModernPerson('john', 5)));
        $this->assertSame('This value should be greater than 18.', $constraintViolationList->get(0)->getMessage());
    }

    public function testBundleSerializationConfigDir()
    {
        static::bootKernel(['test_case' => 'BundlePaths']);
        $serializer = static::getContainer()->get('serializer.alias');

        $this->assertEquals(['full_name' => 'john', 'age' => 5], $serializer->normalize(new LegacyPerson('john', 5), 'json'));
        $this->assertEquals(['full_name' => 'john', 'age' => 5], $serializer->normalize(new ModernPerson('john', 5), 'json'));
    }
}
