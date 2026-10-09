<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlySignUp;
use Symfony\Component\Validator\Validation;

class ConstructorMappingValidationTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()))
            ->getFormFactory();
    }

    public function testConstraintsOfTheArgumentAreReportedOnItsEmptyField()
    {
        $form = $this->createSignUpForm();

        $form->submit(['email' => '', 'birthDate' => '2000-01-02']);

        $this->assertFalse($form->isValid());
        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['This value should not be blank.'], $this->getMessages($form->get('email')));
    }

    public function testInvalidMessageOfAnEmptyFieldIsReportedWhenTheConstraintsOfTheArgumentPass()
    {
        $form = $this->createSignUpForm();

        $form->submit(['email' => 'fabien@example.com', 'birthDate' => '']);

        $this->assertFalse($form->isValid());
        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['Please enter a valid date.'], $this->getMessages($form->get('birthDate')));
    }

    public function testAllEmptyFieldsAreReported()
    {
        $form = $this->createSignUpForm();

        $form->submit(['email' => '', 'birthDate' => '']);

        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['This value should not be blank.'], $this->getMessages($form->get('email')));
        $this->assertSame(['Please enter a valid date.'], $this->getMessages($form->get('birthDate')));
    }

    public function testEmptyFieldIsReportedNextToAnUnsynchronizedField()
    {
        $form = $this->createSignUpForm();

        $form->submit(['email' => '', 'birthDate' => 'tomorrow']);

        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['This value should not be blank.'], $this->getMessages($form->get('email')));
        $this->assertSame(['Please enter a valid date.'], $this->getMessages($form->get('birthDate')));
    }

    public function testEmptyFieldIsReportedWhenTheDataIsReplaced()
    {
        $form = $this->createSignUpForm(new ReadonlySignUp('fabien@example.com', new \DateTimeImmutable('2000-01-02')));

        $form->submit(['email' => '', 'birthDate' => '2000-01-02']);

        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['This value should not be blank.'], $this->getMessages($form->get('email')));
    }

    public function testConstraintsOfTheArgumentAreValidatedInTheGroupsOfTheForm()
    {
        $form = $this->createSignUpForm(null, ['validation_groups' => ['Other']]);

        $form->submit(['email' => '', 'birthDate' => '2000-01-02']);

        $this->assertSame([], $this->getMessages($form));
        $this->assertSame(['This value is not valid.'], $this->getMessages($form->get('email')));
    }

    private function createSignUpForm(?ReadonlySignUp $data = null, array $options = []): FormInterface
    {
        return $this->factory->createBuilder(FormType::class, $data, ['data_class' => ReadonlySignUp::class] + $options)
            ->add('email', TextType::class)
            ->add('birthDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->getForm();
    }

    private function getMessages(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages;
    }
}
