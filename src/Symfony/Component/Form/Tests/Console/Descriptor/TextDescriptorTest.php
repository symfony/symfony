<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Console\Descriptor;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Console\Descriptor\TextDescriptor;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextDescriptorTest extends AbstractDescriptorTestCase
{
    public function testDescribeResolvedFormTypeWithMarkup()
    {
        $type = new class extends AbstractType {
            public function getBlockPrefix(): string
            {
                return 'prefix<info>';
            }

            public function configureOptions(OptionsResolver $resolver): void
            {
                $resolver->setRequired('opt<info>');
            }
        };

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, false);
        (new TextDescriptor())->describe(new SymfonyStyle(new ArrayInput([]), $output), new ResolvedFormType($type), [
            'show_deprecated' => false,
        ]);

        $display = $output->fetch();
        $this->assertStringContainsString('(Block prefix: "prefix<info>")', $display);
        $this->assertStringContainsString('opt<info> (required)', $display);
    }

    public function testDescribeOptionWithMarkup()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('opt<info>', '</error>');
        $resolver->setInfo('opt<info>', 'Use <info>format</info>');

        $type = new class extends AbstractType {};
        foreach ([false, true] as $decorated) {
            $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, $decorated);
            (new TextDescriptor())->describe(new SymfonyStyle(new ArrayInput([]), $output), $resolver, [
                'type' => $type,
                'option' => 'opt<info>',
            ]);

            $display = $output->fetch();
            $this->assertStringContainsString('(opt<info>)', $display);
            $this->assertStringContainsString('Use <info>format</info>', $display);
            $this->assertStringContainsString('</error>', $display);
        }
    }

    protected function getDescriptor()
    {
        return new TextDescriptor();
    }

    protected function getFormat()
    {
        return 'txt';
    }
}
