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
        $kernel = self::bootKernel(['test_case' => 'BundlePaths']);
        $projectDir = sys_get_temp_dir().'/'.uniqid('sf_bundle_paths', true);

        $fs = new Filesystem();
        $fs->mkdir($projectDir.'/public');
        $command = (new Application($kernel))->add(new AssetsInstallCommand($fs, $projectDir));
        $exitCode = (new CommandTester($command))->execute(['target' => $projectDir.'/public']);

        self::assertSame(0, $exitCode);
        self::assertFileExists($projectDir.'/public/bundles/modern/modern.css');
        self::assertFileExists($projectDir.'/public/bundles/legacy/legacy.css');

        $fs->remove($projectDir);
    }

    public function testBundleTwigTemplatesDir()
    {
        self::bootKernel(['test_case' => 'BundlePaths']);
        $twig = self::getContainer()->get('twig.alias');
        $bundlesMetadata = self::getContainer()->getParameter('kernel.bundles_metadata');

        self::assertSame([$bundlesMetadata['LegacyBundle']['path'].'/Resources/views'], $twig->getLoader()->getPaths('Legacy'));
        self::assertSame("OK\n", $twig->render('@Legacy/index.html.twig'));

        self::assertSame([$bundlesMetadata['ModernBundle']['path'].'/templates'], $twig->getLoader()->getPaths('Modern'));
        self::assertSame("OK\n", $twig->render('@Modern/index.html.twig'));
    }

    public function testBundleTranslationsDir()
    {
        self::bootKernel(['test_case' => 'BundlePaths']);
        $translator = self::getContainer()->get('translator.alias');

        self::assertSame('OK', $translator->trans('ok_label', [], 'legacy'));
        self::assertSame('OK', $translator->trans('ok_label', [], 'modern'));
    }

    public function testBundleValidationConfigDir()
    {
        self::bootKernel(['test_case' => 'BundlePaths']);
        $validator = self::getContainer()->get('validator.alias');

        self::assertTrue($validator->hasMetadataFor(LegacyPerson::class));
        self::assertCount(1, $constraintViolationList = $validator->validate(new LegacyPerson('john', 5)));
        self::assertSame('This value should be greater than 18.', $constraintViolationList->get(0)->getMessage());

        self::assertTrue($validator->hasMetadataFor(ModernPerson::class));
        self::assertCount(1, $constraintViolationList = $validator->validate(new ModernPerson('john', 5)));
        self::assertSame('This value should be greater than 18.', $constraintViolationList->get(0)->getMessage());
    }

    public function testBundleSerializationConfigDir()
    {
        self::bootKernel(['test_case' => 'BundlePaths']);
        $serializer = self::getContainer()->get('serializer.alias');

        self::assertEquals(['full_name' => 'john', 'age' => 5], $serializer->normalize(new LegacyPerson('john', 5), 'json'));
        self::assertEquals(['full_name' => 'john', 'age' => 5], $serializer->normalize(new ModernPerson('john', 5), 'json'));
    }
}
