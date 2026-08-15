<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Flow;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Flow\ButtonFlowInterface;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowCursor;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\Flow\Type\NextFlowType;
use Symfony\Component\Form\Flow\Type\PreviousFlowType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\Flow\Data\UserSignUp;
use Symfony\Component\Form\Tests\Fixtures\Flow\Extension\UserSignUpTypeExtension;
use Symfony\Component\Form\Tests\Fixtures\Flow\FirstStepSkippedType;
use Symfony\Component\Form\Tests\Fixtures\Flow\GroupingStepsFlowType;
use Symfony\Component\Form\Tests\Fixtures\Flow\LastStepSkippedType;
use Symfony\Component\Form\Tests\Fixtures\Flow\NestedStepsFlowType;
use Symfony\Component\Form\Tests\Fixtures\Flow\UserSignUpType;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class FormFlowTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory(new LazyLoadingMetadataFactory(new AttributeLoader()))
            ->getValidator();

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions([new ValidatorExtension($validator)])
            ->getFormFactory();
    }

    public function testFlowConfig()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());
        $config = $flow->getConfig();

        self::assertInstanceOf(UserSignUp::class, $data = $config->getData());
        self::assertEquals(['data' => $data], $config->getInitialOptions());
        self::assertCount(3, $config->getSteps());
        self::assertTrue($config->hasStep('personal'));
        self::assertTrue($config->hasStep('professional'));
        self::assertTrue($config->hasStep('account'));
    }

    public function testFlowCursor()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());
        $cursor = $flow->getCursor();

        self::assertSame('personal', $cursor->getCurrentStep());
        self::assertTrue($cursor->isFirstStep());
        self::assertFalse($cursor->isLastStep());
        self::assertSame('personal', $cursor->getFirstStep());
        self::assertNull($cursor->getPreviousStep());
        self::assertSame('professional', $cursor->getNextStep());
        self::assertSame('account', $cursor->getLastStep());
        self::assertSame(['personal', 'professional', 'account'], $cursor->getSteps());
        self::assertSame(0, $cursor->getStepIndex());
        self::assertSame(3, $cursor->getTotalSteps());
        self::assertFalse($cursor->canMoveBack());
        self::assertTrue($cursor->canMoveNext());

        $cursor = $cursor->withCurrentStep('professional');

        self::assertSame('professional', $cursor->getCurrentStep());
        self::assertFalse($cursor->isFirstStep());
        self::assertFalse($cursor->isLastStep());
        self::assertSame('personal', $cursor->getFirstStep());
        self::assertSame('personal', $cursor->getPreviousStep());
        self::assertSame('account', $cursor->getNextStep());
        self::assertSame('account', $cursor->getLastStep());
        self::assertSame(1, $cursor->getStepIndex());
        self::assertSame(3, $cursor->getTotalSteps());
        self::assertTrue($cursor->canMoveBack());
        self::assertTrue($cursor->canMoveNext());

        $cursor = $cursor->withCurrentStep('account');

        self::assertSame('account', $cursor->getCurrentStep());
        self::assertFalse($cursor->isFirstStep());
        self::assertTrue($cursor->isLastStep());
        self::assertSame('personal', $cursor->getFirstStep());
        self::assertSame('professional', $cursor->getPreviousStep());
        self::assertNull($cursor->getNextStep());
        self::assertSame('account', $cursor->getLastStep());
        self::assertSame(2, $cursor->getStepIndex());
        self::assertSame(3, $cursor->getTotalSteps());
        self::assertTrue($cursor->canMoveBack());
        self::assertFalse($cursor->canMoveNext());
    }

    public function testFlowViewVars()
    {
        $view = $this->factory->create(UserSignUpType::class, new UserSignUp())
            ->createView();

        self::assertArrayHasKey('steps', $view->vars);
        self::assertArrayHasKey('visible_steps', $view->vars);

        self::assertCount(3, $view->vars['steps']);
        self::assertCount(2, $view->vars['visible_steps']);

        self::assertArrayHasKey('personal', $view->vars['steps']);
        self::assertArrayHasKey('professional', $view->vars['steps']);
        self::assertArrayHasKey('account', $view->vars['steps']);
        self::assertArrayHasKey('personal', $view->vars['visible_steps']);
        self::assertArrayHasKey('account', $view->vars['visible_steps']);

        $step1 = [
            'name' => 'personal',
            'level' => 0,
            'index' => 0,
            'position' => 1,
            'is_before_current_step' => false,
            'is_current_step' => true,
            'has_current_step_descendant' => false,
            'is_after_current_step' => false,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];
        $step2 = [
            'name' => 'professional',
            'level' => 0,
            'index' => 1,
            'position' => -1,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => true,
            'is_skipped' => true,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];
        $step3 = [
            'name' => 'account',
            'level' => 0,
            'index' => 2,
            'position' => 2,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];

        self::assertSame($step1, $view->vars['steps']['personal']);
        self::assertSame($step2, $view->vars['steps']['professional']);
        self::assertSame($step3, $view->vars['steps']['account']);
        self::assertSame($step1, $view->vars['visible_steps']['personal']);
        self::assertSame($step3, $view->vars['visible_steps']['account']);
    }

    public function testWholeStepsFlow()
    {
        $data = new UserSignUp();
        $flow = $this->factory->create(UserSignUpType::class, $data);

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('personal'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('personal');
        self::assertCount(3, $stepForm->all());
        self::assertTrue($stepForm->has('firstName'));
        self::assertTrue($stepForm->has('lastName'));
        self::assertTrue($stepForm->has('worker'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(2, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('next'));

        $flow->submit([
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'worker' => '1',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertTrue($button->isClicked());

        $flow = $flow->getStepForm();

        self::assertSame('professional', $data->currentStep);
        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('professional'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('professional');
        self::assertCount(2, $stepForm->all());
        self::assertTrue($stepForm->has('company'));
        self::assertTrue($stepForm->has('role'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(4, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('previous'));
        self::assertTrue($navigatorForm->has('skip'));
        self::assertTrue($navigatorForm->has('next'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertTrue($button->isClicked());

        $flow = $flow->getStepForm();

        /** @var UserSignUp $data */
        $data = $flow->getViewData();
        self::assertSame('account', $data->currentStep);
        self::assertSame('account', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('account'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('account');
        self::assertCount(2, $stepForm->all());
        self::assertTrue($stepForm->has('email'));
        self::assertTrue($stepForm->has('password'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(3, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('previous'));
        self::assertTrue($navigatorForm->has('finish'));

        $flow->submit([
            'account' => [
                'email' => 'john@acme.com',
                'password' => 'eBvU2vBLfSXqf36',
            ],
            'navigator' => [
                'finish' => '',
            ],
        ]);

        self::assertSame('account', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertTrue($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isFinishAction());
        self::assertTrue($button->isClicked());

        self::assertSame($data, $flow->getViewData());
        self::assertSame('John', $data->firstName);
        self::assertSame('Doe', $data->lastName);
        self::assertTrue($data->worker);
        self::assertSame('Acme', $data->company);
        self::assertSame('ROLE_DEVELOPER', $data->role);
        self::assertSame('john@acme.com', $data->email);
        self::assertSame('eBvU2vBLfSXqf36', $data->password);
    }

    public function testPreviousActionWithPurgeSubmission()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';
        $flow = $this->factory->create(UserSignUpType::class, $data);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'previous' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isPreviousAction());

        $flow = $flow->getStepForm();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'), 'back action should move the flow one step back');
        self::assertNull($data->company, 'pro step should be silenced on submit');
        self::assertNull($data->role, 'pro step should be silenced on submit');
    }

    public function testPreviousActionWithoutPurgeSubmission()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';

        $flow = $this->factory->create(UserSignUpType::class, $data);
        // previous action without purge submission
        $flow->get('navigator')->add('previous', PreviousFlowType::class, [
            'validate' => false,
            'validation_groups' => false,
            'clear_submission' => false,
            'include_if' => static fn (FormFlowCursor $cursor) => $cursor->canMoveBack(),
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'previous' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isPreviousAction());

        $flow = $flow->getStepForm();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'), 'previous action should move the flow one step back');
        self::assertSame('Acme', $data->company, 'pro step should NOT be silenced on submit');
        self::assertSame('ROLE_DEVELOPER', $data->role, 'pro step should NOT be silenced on submit');
    }

    public function testSkipStepBasedOnData()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'));

        $flow->submit([
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                // worker checkbox was not clicked
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());

        $flow = $flow->getStepForm();

        self::assertFalse($flow->has('professional'), 'pro step should be skipped');
        self::assertSame('account', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('account'));
    }

    public function testResetAction()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';

        $dataStorage = new InMemoryDataStorage('user_sign_up');
        $dataStorage->save($data);

        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp(), [
            'data_storage' => $dataStorage,
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'reset' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isResetAction());

        $flow = $flow->getStepForm();
        /** @var UserSignUp $data */
        $data = $flow->getViewData();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'), 'reset action should move the flow to the initial step');
        self::assertNull($data->firstName);
        self::assertNull($data->lastName);
        self::assertFalse($data->worker);
        self::assertNull($data->company);
        self::assertNull($data->role);
    }

    public function testResetManually()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';

        $dataStorage = new InMemoryDataStorage('user_sign_up');
        $dataStorage->save($data);

        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp(), [
            'data_storage' => $dataStorage,
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());

        $flow->reset();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
    }

    public function testSkipAction()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';

        $dataStorage = new InMemoryDataStorage('user_sign_up');
        $dataStorage->save($data);

        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp(), [
            'data_storage' => $dataStorage,
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'skip' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertSame('skip', $button->getName());

        $flow = $flow->getStepForm();
        /** @var UserSignUp $data */
        $data = $flow->getViewData();

        self::assertSame('account', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('account'), 'skip action should move the flow to the next step but skip submitted data and clear');
        self::assertSame('John', $data->firstName);
        self::assertSame('Doe', $data->lastName);
        self::assertTrue($data->worker);
        self::assertNull($data->company);
        self::assertNull($data->role);
    }

    public function testTypeExtensionAndStepsPriority()
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new UserSignUpTypeExtension())
            ->getFormFactory();

        $flow = $factory->create(UserSignUpType::class, new UserSignUp());

        self::assertSame('first', $flow->getCursor()->getCurrentStep());
        self::assertSame(['first', 'personal', 'professional', 'account', 'last'], $flow->getCursor()->getSteps());
    }

    public function testMoveBackToStep()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->company = 'Acme';
        $data->role = 'ROLE_DEVELOPER';
        $data->currentStep = 'account';

        $flow = $this->factory->create(UserSignUpType::class, $data);
        $flow->get('navigator')->add('back_to_step', PreviousFlowType::class, [
            'validate' => false,
            'validation_groups' => false,
            'clear_submission' => false,
        ]);

        self::assertSame('account', $flow->getCursor()->getCurrentStep());

        $flow->submit([
            'account' => [
                'email' => 'jdoe@acme.com',
                'password' => '$ecret',
            ],
            'navigator' => [
                'back_to_step' => 'personal',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isPreviousAction());
        self::assertSame('personal', $button->getViewData());

        $flow = $flow->getStepForm();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'));
        self::assertSame('John', $data->firstName);
        self::assertSame('Acme', $data->company);
        self::assertSame('jdoe@acme.com', $data->email);
    }

    public function testMoveManually()
    {
        $data = new UserSignUp();
        $data->firstName = 'John';
        $data->lastName = 'Doe';
        $data->worker = true;
        $data->currentStep = 'professional';

        $dataStorage = new InMemoryDataStorage('user_sign_up');
        $dataStorage->save($data);

        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp(), [
            'data_storage' => $dataStorage,
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));

        $flow->movePrevious();
        $flow = $flow->newStepForm();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'));

        $flow->moveNext();
        $flow = $flow->newStepForm();

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('professional'));
    }

    public function testInvalidMovePreviousUntilAheadStep()
    {
        $data = new UserSignUp();
        $data->currentStep = 'personal';
        $flow = $this->factory->create(UserSignUpType::class, $data);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot move back to step "account" because it is ahead of the current step "personal".');

        $flow->movePrevious('account');
    }

    public function testInvalidMovePreviousUntilSkippedStep()
    {
        $data = new UserSignUp();
        $data->worker = false;
        $data->currentStep = 'account';
        $flow = $this->factory->create(UserSignUpType::class, $data);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot move back to step "professional" because it is a skipped step.');

        $flow->movePrevious('professional');
    }

    public function testInvalidStepForm()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'));

        $flow->submit([
            'personal' => [
                'firstName' => '', // This value should not be blank
                'lastName' => 'Doe',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertFalse($flow->isValid());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertSame($flow, $flow->getStepForm());
        self::assertSame('This value should not be blank.', $flow->getErrors(true)->current()->getMessage());
    }

    public function testCannotModifyStepConfigAfterFormBuilding()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('StepFlowBuilder methods cannot be accessed anymore once the builder is turned into a StepFlowConfigInterface instance.');

        $flow->getConfig()->getStep('personal')->setPriority(0);
    }

    public function testIgnoreSubmissionIfStepIsMissing()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('personal'));

        $flow->submit([
            'account' => [
                'firstName' => '',
                'lastName' => '',
            ],
            'navigator' => [
                'previous' => '',
            ],
        ]);

        self::assertFalse($flow->isSubmitted());
    }

    public function testViewVars()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());
        $view = $flow->createView();

        self::assertInstanceOf(FormFlowCursor::class, $view->vars['cursor']);
        self::assertCount(3, $view->vars['steps']);
        self::assertSame(['personal', 'professional', 'account'], array_keys($view->vars['steps']));
        self::assertSame('personal', $view->vars['steps']['personal']['name']);
        self::assertTrue($view->vars['steps']['personal']['is_current_step']);
        self::assertFalse($view->vars['steps']['personal']['is_skipped']);
        self::assertSame('professional', $view->vars['steps']['professional']['name']);
        self::assertFalse($view->vars['steps']['professional']['is_current_step']);
        self::assertTrue($view->vars['steps']['professional']['is_skipped']);
        self::assertSame('account', $view->vars['steps']['account']['name']);
        self::assertFalse($view->vars['steps']['account']['is_current_step']);
        self::assertFalse($view->vars['steps']['account']['is_skipped']);
    }

    public function testFallbackCurrentStep()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        /** @var UserSignUp $data */
        $data = $flow->getViewData();

        self::assertSame('personal', $flow->getCursor()->getCurrentStep(), 'The current step should be the first one depending on the step priority');
        self::assertSame('personal', $data->currentStep);
    }

    public function testInitialCurrentStep()
    {
        $data = new UserSignUp();
        $data->currentStep = 'professional';
        $flow = $this->factory->create(UserSignUpType::class, $data);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep(), 'The current step should be the one set in the initial data');
        self::assertSame('professional', $data->currentStep);
    }

    public function testFormFlowWithArrayData()
    {
        $flow = $this->factory->create(UserSignUpType::class, [], [
            'data_class' => null,
            'step_property_path' => '[currentStep]',
        ]);

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('personal'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('personal');
        self::assertCount(3, $stepForm->all());
        self::assertTrue($stepForm->has('firstName'));
        self::assertTrue($stepForm->has('lastName'));
        self::assertTrue($stepForm->has('worker'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(2, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('next'));

        $flow->submit([
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'worker' => '1',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertTrue($button->isClicked());

        $flow = $flow->getStepForm();

        $data = $flow->getData();
        self::assertSame('professional', $data['currentStep']);
        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('professional'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('professional');
        self::assertCount(2, $stepForm->all());
        self::assertTrue($stepForm->has('company'));
        self::assertTrue($stepForm->has('role'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(4, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('previous'));
        self::assertTrue($navigatorForm->has('skip'));
        self::assertTrue($navigatorForm->has('next'));

        $flow->submit([
            'professional' => [
                'company' => 'Acme',
                'role' => 'ROLE_DEVELOPER',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertTrue($button->isClicked());

        $flow = $flow->getStepForm();

        $data = $flow->getData();
        self::assertSame('account', $data['currentStep']);
        self::assertSame('account', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('account'));
        self::assertTrue($flow->has('navigator'));

        $stepForm = $flow->get('account');
        self::assertCount(2, $stepForm->all());
        self::assertTrue($stepForm->has('email'));
        self::assertTrue($stepForm->has('password'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(3, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('reset'));
        self::assertTrue($navigatorForm->has('previous'));
        self::assertTrue($navigatorForm->has('finish'));

        $flow->submit([
            'account' => [
                'email' => 'john@acme.com',
                'password' => 'eBvU2vBLfSXqf36',
            ],
            'navigator' => [
                'finish' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertTrue($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isFinishAction());
        self::assertTrue($button->isClicked());
        self::assertSame('personal', $flow->getCursor()->getCurrentStep());

        $data = $flow->getData();
        self::assertSame('John', $data['firstName']);
        self::assertSame('Doe', $data['lastName']);
        self::assertTrue($data['worker']);
        self::assertSame('Acme', $data['company']);
        self::assertSame('ROLE_DEVELOPER', $data['role']);
        self::assertSame('john@acme.com', $data['email']);
        self::assertSame('eBvU2vBLfSXqf36', $data['password']);
    }

    public function testHandleActionManually()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());

        $flow->submit([
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'worker' => '1',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertNotNull($actionButton = $flow->getClickedButton());
        self::assertSame('personal', $flow->getCursor()->getCurrentStep());

        $actionButton->handle();

        self::assertSame('professional', $flow->getCursor()->getCurrentStep());
    }

    public function testAddFormErrorOnActionHandling()
    {
        $flow = $this->factory->create(UserSignUpType::class, new UserSignUp());
        $flow->get('navigator')->add('next', NextFlowType::class, [
            'handler' => static function (mixed $data, ButtonFlowInterface $button, FormFlowInterface $flow) {
                $flow->addError(new FormError('Action error'));
            },
        ]);

        self::assertSame('personal', $flow->getCursor()->getCurrentStep());

        $flow->submit([
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertNotNull($actionButton = $flow->getClickedButton());
        self::assertSame('personal', $flow->getCursor()->getCurrentStep());

        $actionButton->handle();
        $flow = $flow->getStepForm();
        $errors = $flow->getErrors(true);

        self::assertFalse($flow->isValid());
        self::assertCount(1, $errors);
        self::assertSame('Action error', $errors->current()->getMessage());
        self::assertSame('personal', $flow->getCursor()->getCurrentStep());
    }

    public function testStepValidationGroups()
    {
        $data = new UserSignUp();
        $data->worker = true;
        $flow = $this->factory->create(UserSignUpType::class, $data);

        // Check that validation groups include the current step name
        self::assertSame(['Default', 'personal'], $flow->getConfig()->getOption('validation_groups')($flow));

        // Move to next step
        $flow->moveNext();
        $flow = $flow->newStepForm();

        // Check that validation groups are updated
        self::assertEquals(['Default', 'professional'], $flow->getConfig()->getOption('validation_groups')($flow));
    }

    public function testLastStepSkippedMarkFlowAsFinished()
    {
        $flow = $this->factory->create(LastStepSkippedType::class, ['currentStep' => 'step1']);

        self::assertSame('step1', $flow->getCursor()->getCurrentStep());
        self::assertFalse($flow->isSubmitted());
        self::assertNull($flow->getClickedButton());
        self::assertTrue($flow->has('step1'));
        self::assertTrue($flow->has('navigator'));

        $navigatorForm = $flow->get('navigator');
        self::assertCount(2, $navigatorForm->all());
        self::assertTrue($navigatorForm->has('next'));
        self::assertTrue($navigatorForm->has('reset'));

        $flow->submit([
            'step1' => 'foo',
            'navigator' => [
                'next' => '',
            ],
        ]);

        self::assertSame('step1', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertFalse($flow->isFinished());
        self::assertNotNull($button = $flow->getClickedButton());
        self::assertTrue($button->isNextAction());
        self::assertTrue($button->isClicked());

        $button->handle(); // $flow->moveNext() is called internally

        self::assertTrue($flow->isFinished());
        self::assertNotSame($flow, $flow->getStepForm());
        self::assertSame(['currentStep' => 'step1', 'step1' => 'foo'], $flow->getData());
    }

    public function testNestedStepsFlowConfig()
    {
        $flow = $this->factory->create(NestedStepsFlowType::class, []);
        $config = $flow->getConfig();

        self::assertTrue($config->hasStep('stepA'));
        self::assertTrue($config->hasStep('stepA1'));
        self::assertTrue($config->hasStep('stepA2'));
        self::assertTrue($config->hasStep('stepA3'));
        self::assertTrue($config->hasStep('stepB'));
        self::assertTrue($config->hasStep('stepB1'));
        self::assertTrue($config->hasStep('stepB11'));
        self::assertTrue($config->hasStep('stepB12'));
        self::assertTrue($config->hasStep('stepB2'));
        self::assertTrue($config->hasStep('stepC'));
    }

    public function testNestedStepsFlowNavigation()
    {
        $flow = $this->factory->create(NestedStepsFlowType::class, []);

        // stepA is group, so the initial step is its first child
        self::assertSame('stepA1', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepA1'));

        $flow->submit([
            'stepA1' => 'value1',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepA2', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepA2'));

        $flow->submit([
            'stepA2' => 'value2',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepA3', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepA3'));

        $flow->submit([
            'stepA3' => 'value3',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepB', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB'));

        // Submit stepB with value 2 (SkipB2) - this means stepB1 is NOT skipped
        $flow->submit([
            'stepB' => '2',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        // stepB1 is NOT skipped (skip condition returns false), it's a FormType container
        self::assertSame('stepB1', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB1'));

        $flow->submit([
            'stepB1' => [],
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepB11', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB11'));

        $flow->submit([
            'stepB11' => 'valueB11',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepB12', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB12'));

        $flow->submit([
            'stepB12' => 'valueB12',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepB2', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB2'));

        $flow->submit([
            'stepB2' => 'valueB2',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepC', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepC'));
        self::assertTrue($flow->getCursor()->isLastStep());

        // Finish the flow
        $flow->submit([
            'stepC' => 'valueC',
            'navigator' => ['finish' => ''],
        ]);

        self::assertTrue($flow->isSubmitted());
        self::assertTrue($flow->isValid());
        self::assertTrue($flow->isFinished());

        // Verify all data was collected
        $data = $flow->getData();
        self::assertSame('value1', $data['stepA1']);
        self::assertSame('value2', $data['stepA2']);
        self::assertSame('value3', $data['stepA3']);
        self::assertSame(2, $data['stepB']); // ChoiceType converts to int
        self::assertSame('valueB11', $data['stepB11']);
        self::assertSame('valueB12', $data['stepB12']);
        self::assertSame('valueB2', $data['stepB2']);
        self::assertSame('valueC', $data['stepC']);
    }

    public function testNestedStepsFlowNavigationWithSkippedParent()
    {
        $flow = $this->factory->create(NestedStepsFlowType::class, []);

        // Navigate quickly to stepB
        $flow->submit(['stepA' => [], 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        $flow->submit(['stepA1' => 'v1', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        $flow->submit(['stepA2' => 'v2', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        $flow->submit(['stepA3' => 'v3', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();

        self::assertSame('stepB', $flow->getCursor()->getCurrentStep());

        // Submit stepB with value 1 (SkipB1) - stepB1 parent is skipped but children are not
        $flow->submit([
            'stepB' => '1',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        // stepB1 is skipped via skip func, so its children (stepB11, stepB12) are also skipped
        self::assertSame('stepB2', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('stepB2'));

        $flow->submit([
            'stepB2' => 'valueB2',
            'navigator' => ['next' => ''],
        ]);

        $flow = $flow->getStepForm();

        self::assertSame('stepC', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->getCursor()->isLastStep());
    }

    public function testNestedStepsViewVars()
    {
        $flow = $this->factory->create(NestedStepsFlowType::class, []);
        $view = $flow->createView();

        // Top-level steps (3 in this case)
        self::assertArrayHasKey('steps', $view->vars);
        self::assertCount(3, $view->vars['steps']);
        self::assertArrayHasKey('stepA', $view->vars['steps']);
        self::assertArrayHasKey('stepB', $view->vars['steps']);
        self::assertArrayHasKey('stepC', $view->vars['steps']);

        // Top-level index and position are level-specific
        // stepA uses FormType (container) so it's skipped (position=-1)
        self::assertSame(0, $view->vars['steps']['stepA']['index']);
        self::assertSame(1, $view->vars['steps']['stepA']['position']);
        self::assertFalse($view->vars['steps']['stepA']['is_skipped']);
        // stepB uses ChoiceType, stepC uses TextType - both visible
        self::assertSame(1, $view->vars['steps']['stepB']['index']);
        self::assertSame(2, $view->vars['steps']['stepB']['position']);
        self::assertFalse($view->vars['steps']['stepB']['is_skipped']);
        self::assertSame(2, $view->vars['steps']['stepC']['index']);
        self::assertSame(3, $view->vars['steps']['stepC']['position']);
        self::assertFalse($view->vars['steps']['stepC']['is_skipped']);

        // has_current_step_descendant is true for current step (stepA1 is the initial step)
        self::assertTrue($view->vars['steps']['stepA']['has_current_step_descendant']);
        self::assertFalse($view->vars['steps']['stepB']['has_current_step_descendant']);
        self::assertFalse($view->vars['steps']['stepC']['has_current_step_descendant']);

        // stepA children (stepA1, stepA2, stepA3) have level-specific index/position
        $stepAChildren = $view->vars['steps']['stepA']['children'];
        self::assertCount(3, $stepAChildren);
        self::assertSame(0, $stepAChildren['stepA1']['index']);
        self::assertSame(1, $stepAChildren['stepA1']['position']);
        self::assertSame(1, $stepAChildren['stepA2']['index']);
        self::assertSame(2, $stepAChildren['stepA2']['position']);
        self::assertSame(2, $stepAChildren['stepA3']['index']);
        self::assertSame(3, $stepAChildren['stepA3']['position']);

        // stepB children (stepB1, stepB2)
        $stepBChildren = $view->vars['steps']['stepB']['children'];
        self::assertCount(2, $stepBChildren);
        // stepB1 has explicit setSkip that checks $data['stepB'] === 1, which is false for empty data
        self::assertSame(0, $stepBChildren['stepB1']['index']);
        self::assertSame(1, $stepBChildren['stepB1']['position']);
        self::assertFalse($stepBChildren['stepB1']['is_skipped']);
        // stepB2 is a TextType, so it's visible
        self::assertSame(1, $stepBChildren['stepB2']['index']);
        self::assertSame(2, $stepBChildren['stepB2']['position']);
        self::assertFalse($stepBChildren['stepB2']['is_skipped']);

        // stepB1 nested children (stepB11, stepB12)
        $stepB1Children = $stepBChildren['stepB1']['children'];
        self::assertCount(2, $stepB1Children);
        self::assertSame(0, $stepB1Children['stepB11']['index']);
        self::assertSame(1, $stepB1Children['stepB11']['position']);
        self::assertSame(1, $stepB1Children['stepB12']['index']);
        self::assertSame(2, $stepB1Children['stepB12']['position']);
    }

    public function testNestedStepsViewVarsWithDeeplyNestedCurrentStep()
    {
        // Set current step to a deeply nested child (stepB11)
        $flow = $this->factory->create(NestedStepsFlowType::class, ['currentStep' => 'stepB11']);
        $view = $flow->createView();

        // All ancestors should have has_current_step_descendant=true
        self::assertFalse($view->vars['steps']['stepA']['has_current_step_descendant']);
        self::assertTrue($view->vars['steps']['stepB']['has_current_step_descendant']);
        self::assertFalse($view->vars['steps']['stepC']['has_current_step_descendant']);

        // stepB1 (parent of stepB11) should have has_current_step_descendant=true
        $stepBChildren = $view->vars['steps']['stepB']['children'];
        self::assertTrue($stepBChildren['stepB1']['has_current_step_descendant']);
        self::assertFalse($stepBChildren['stepB2']['has_current_step_descendant']);

        // stepB11 should have is_current_step=true
        $stepB1Children = $stepBChildren['stepB1']['children'];
        self::assertTrue($stepB1Children['stepB11']['is_current_step']);
        self::assertFalse($stepB1Children['stepB12']['is_current_step']);
    }

    public function testGroupViewVars()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);
        $view = $flow->createView();

        // Current step is 'a1' (first visitable child of group 'a')
        self::assertSame('a1', $flow->getCursor()->getCurrentStep());

        // Top-level: a(group), b, c(group)
        self::assertCount(3, $view->vars['steps']);
        self::assertArrayHasKey('a', $view->vars['steps']);
        self::assertArrayHasKey('b', $view->vars['steps']);
        self::assertArrayHasKey('c', $view->vars['steps']);

        $a1 = [
            'name' => 'a1',
            'level' => 1,
            'index' => 0,
            'position' => 1,
            'is_before_current_step' => false,
            'is_current_step' => true,
            'has_current_step_descendant' => false,
            'is_after_current_step' => false,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];
        $a2 = [
            'name' => 'a2',
            'level' => 1,
            'index' => 1,
            'position' => 2,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];
        $c1 = [
            'name' => 'c1',
            'level' => 1,
            'index' => 0,
            'position' => 1,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ];

        // Group 'a': level 0, before current step, has_current_step_descendant (a1 is current)
        self::assertSame([
            'name' => 'a',
            'level' => 0,
            'index' => 0,
            'position' => 1,
            'is_before_current_step' => true,
            'is_current_step' => false,
            'has_current_step_descendant' => true,
            'is_after_current_step' => false,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => true,
            'children' => ['a1' => $a1, 'a2' => $a2],
            'visible_children' => ['a1' => $a1, 'a2' => $a2],
        ], $view->vars['steps']['a']);

        // Step 'b': level 0, after current step
        self::assertSame([
            'name' => 'b',
            'level' => 0,
            'index' => 1,
            'position' => 2,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => false,
            'children' => [],
            'visible_children' => [],
        ], $view->vars['steps']['b']);

        // Group 'c': level 0, after current step
        self::assertSame([
            'name' => 'c',
            'level' => 0,
            'index' => 2,
            'position' => 3,
            'is_before_current_step' => false,
            'is_current_step' => false,
            'has_current_step_descendant' => false,
            'is_after_current_step' => true,
            'can_be_skipped' => false,
            'is_skipped' => false,
            'is_group' => true,
            'children' => ['c1' => $c1],
            'visible_children' => ['c1' => $c1],
        ], $view->vars['steps']['c']);
    }

    public function testGroupFirstRootResolvesToFirstChild()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);

        // 'a' is group, initial step is 'a1' (first child)
        self::assertSame('a1', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('a1'));
    }

    public function testGroupForwardNavigationSkipsToChildren()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);

        // Start at a1 (skipping group 'a')
        self::assertSame('a1', $flow->getCursor()->getCurrentStep());

        $flow->submit(['a1' => 'v1', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('a2', $flow->getCursor()->getCurrentStep());

        $flow->submit(['a2' => 'v2', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();

        self::assertSame('b', $flow->getCursor()->getCurrentStep());
    }

    public function testGroupForwardFromLastNonGroupStepToGroupChild()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);

        // Navigate to 'b'
        $flow->submit(['a1' => 'v1', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        $flow->submit(['a2' => 'v2', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('b', $flow->getCursor()->getCurrentStep());

        // Move forward from 'b': next is 'c' (group/skipped) → 'c1' (first child)
        $flow->submit(['b' => 'v3', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('c1', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('c1'));
    }

    public function testGroupBackwardNavigationSkipsParent()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);

        // Navigate forward to a2
        $flow->submit(['a1' => 'v1', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('a2', $flow->getCursor()->getCurrentStep());

        // Navigate forward to b
        $flow->submit(['a2' => 'v2', 'navigator' => ['next' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('b', $flow->getCursor()->getCurrentStep());

        // Move backward from 'b': previous in DFS is 'a2' (deepest last descendant of 'a')
        // 'a' is group/skipped, so move() lands on 'a2'
        $flow->submit(['b' => 'v3', 'navigator' => ['previous' => '']]);
        $flow = $flow->getStepForm();
        self::assertSame('a2', $flow->getCursor()->getCurrentStep());
    }

    public function testGroupBackwardFromFirstChildOfGroupRootThrows()
    {
        $flow = $this->factory->create(GroupingStepsFlowType::class, []);

        self::assertSame('a1', $flow->getCursor()->getCurrentStep());

        // Structurally there is a previous step ('a'), but it's group
        // and there's nothing before it, so moving backward fails
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine previous step.');

        $flow->movePrevious();
    }

    public function testFirstStepSkippedResolvesToSecondStep()
    {
        $flow = $this->factory->create(FirstStepSkippedType::class, []);

        // step1 is skipped, initial step resolves to step2
        self::assertSame('step2', $flow->getCursor()->getCurrentStep());
        self::assertTrue($flow->has('step2'));
    }
}
