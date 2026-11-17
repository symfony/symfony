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
use Symfony\Component\Form\Console\Descriptor\JsonDescriptor;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonDescriptorTest extends AbstractDescriptorTestCase
{
    public function testDescribeOptionWithMarkupAndDecoratedOutput()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('my_option', 'value<comment>end</comment>');
        $resolver->setInfo('my_option', 'Use <info>format</info>');

        $type = new class extends AbstractType {};
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        (new JsonDescriptor())->describe(new SymfonyStyle(new ArrayInput([]), $output), $resolver, [
            'type' => $type,
            'option' => 'my_option',
        ]);

        $data = json_decode($output->fetch(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('Use <info>format</info>', $data['info']);
        $this->assertSame('value<comment>end</comment>', $data['default']);
    }

    public function testDescribeOptionWithInvalidUtf8()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('my_option', "bad\xFF");

        $type = new class extends AbstractType {};
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, false);
        (new JsonDescriptor())->describe(new SymfonyStyle(new ArrayInput([]), $output), $resolver, [
            'type' => $type,
            'option' => 'my_option',
        ]);

        $data = json_decode($output->fetch(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame("bad\u{FFFD}", $data['default']);
    }

    protected function getDescriptor()
    {
        return new JsonDescriptor();
    }

    protected function getFormat()
    {
        return 'json';
    }
}
