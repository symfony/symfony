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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

abstract class AbstractDescriptorTest extends TestCase
{
    /** @dataProvider getDescribeDefaultsTestData */
    public function testDescribeDefaults($object, array $options, $fixtureName)
    {
        $expectedDescription = $this->getExpectedDescription($fixtureName);
        $describedObject = $this->getObjectDescription($object, $options);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), JSON_PRETTY_PRINT), json_encode(json_decode($describedObject), JSON_PRETTY_PRINT));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $describedObject)));
        }
    }

    /** @dataProvider getDescribeResolvedFormTypeTestData */
    public function testDescribeResolvedFormType(ResolvedFormTypeInterface $type, array $options, $fixtureName)
    {
        $expectedDescription = $this->getExpectedDescription($fixtureName);
        $describedObject = $this->getObjectDescription($type, $options);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), JSON_PRETTY_PRINT), json_encode(json_decode($describedObject), JSON_PRETTY_PRINT));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $describedObject)));
        }
    }

    public function getDescribeDefaultsTestData()
    {
        $options['types'] = array('Symfony\Bridge\Doctrine\Form\Type\EntityType');
        $options['extensions'] = array('Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension');
        $options['guessers'] = array('Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser');

        yield array(null, $options, 'defaults_1');
    }

    public function getDescribeResolvedFormTypeTestData()
    {
        $typeExtensions = array(
            new FormTypeCsrfExtension(new CsrfTokenManager()),
        );
        $parent = new ResolvedFormType(new FormType(), $typeExtensions);

        yield array(new ResolvedFormType(new ChoiceType(), array(), $parent), array(), 'resolved_form_type_1');
    }

    abstract protected function getDescriptor();

    abstract protected function getFormat();

    private function getObjectDescription($object, array $options = array())
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $io = new SymfonyStyle(new ArrayInput(array()), $output);

        $this->getDescriptor()->describe($io, $object, $options);

        return $output->fetch();
    }

    private function getExpectedDescription($name)
    {
        return file_get_contents($this->getFixtureFilename($name));
    }

    private function getFixtureFilename($name)
    {
        return sprintf('%s/../../Fixtures/Descriptor/%s.%s', __DIR__, $name, $this->getFormat());
    }
}
