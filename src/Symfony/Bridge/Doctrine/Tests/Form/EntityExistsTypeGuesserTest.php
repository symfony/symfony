<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityFieldValueChoiceLoader;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Bridge\Doctrine\Form\EntityExistsTypeGuesser;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Middleware;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EntityExistsFieldsChildDto;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EntityExistsFieldsDto;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EntityExistsFieldsEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EntityExistsFieldsFilter;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EntityExistsFieldsRepository;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\StringableDateTime;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExistsValidator;
use Symfony\Component\Form\EnumFormTypeGuesser;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class EntityExistsTypeGuesserTest extends TestCase
{
    private DebugDataHolder $queries;
    private EntityManager $em;
    private ManagerRegistry $registry;
    private EntityExistsTypeGuesser $guesser;
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $config = DoctrineTestHelper::createTestConfiguration();
        DoctrineTestHelper::registerTypes($config, ['uuid' => UuidType::class, 'ulid' => UlidType::class]);
        $config->setMiddlewares([new Middleware($this->queries = new DebugDataHolder(), null)]);

        $this->em = DoctrineTestHelper::createTestEntityManager($config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(EntityExistsFieldsEntity::class),
            $this->em->getClassMetadata(SingleIntIdEntity::class),
        ]);

        foreach ([1 => 0, 2 => 1, 3 => 0, 4 => 1] as $id => $k) {
            $this->em->persist(self::createEntity($id, $k));
        }

        $entity = self::createEntity(5, 0);
        $entity->string = null;
        $entity->asciiString = '';
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();

        $this->registry = $this->createRegistry($this->em);
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([EntityExistsValidator::class => new EntityExistsValidator($this->registry)]))
            ->getValidator();

        $this->guesser = new EntityExistsTypeGuesser($this->registry, $validator);
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addTypeGuesser($this->guesser)
            ->getFormFactory();
        $this->queries->reset();
    }

    protected function tearDown(): void
    {
        EntityExistsFieldsRepository::$maxId = null;
        if (isset($this->em)) {
            $this->em->close();
        }
    }

    #[DataProvider('provideGuessedProperties')]
    public function testChoicesAreTheDistinctValuesOfTheField(string $property, string $phpType, array $values, mixed $modelValue)
    {
        $guess = $this->guesser->guessType(EntityExistsFieldsDto::class, $property);

        $this->assertSame(ChoiceType::class, $guess->getType());
        $this->assertSame(Guess::VERY_HIGH_CONFIDENCE, $guess->getConfidence());

        $view = $this->createForm($property)->createView()[$property];

        $this->assertSame($values, array_map(static fn ($choice) => $choice->value, array_values($view->vars['choices'])));
        $this->assertFalse($view->vars['choice_translation_domain']);
    }

    #[DataProvider('provideGuessedProperties')]
    public function testSubmittedValueIsConvertedToThePhpTypeOfTheField(string $property, string $phpType, array $values, mixed $modelValue)
    {
        $form = $this->createForm($property, $dto = new EntityExistsFieldsDto());
        $form->submit([$property => $values[1]]);

        $this->assertTrue($form->get($property)->isValid(), (string) $form->get($property)->getErrors());
        $this->assertTrue(get_debug_type($dto->$property) === $phpType || $dto->$property instanceof $phpType, get_debug_type($dto->$property));
        $this->assertSame($values[1], $form->createView()[$property]->vars['value']);
    }

    #[DataProvider('provideGuessedProperties')]
    public function testEqualModelValueSelectsItsChoice(string $property, string $phpType, array $values, mixed $modelValue)
    {
        $dto = new EntityExistsFieldsDto();
        $dto->$property = $modelValue;

        $this->assertSame($values[1], $this->createForm($property, $dto)->createView()[$property]->vars['value']);
    }

    #[DataProvider('provideGuessedProperties')]
    public function testEmptySubmissionIsNull(string $property, string $phpType, array $values, mixed $modelValue)
    {
        $dto = new EntityExistsFieldsDto();
        $dto->$property = $modelValue;
        $form = $this->createForm($property, $dto);
        $form->submit([]);

        $this->assertTrue($form->get($property)->isValid());
        $this->assertNull($dto->$property);
    }

    public static function provideGuessedProperties(): iterable
    {
        yield 'boolean' => ['boolean', 'bool', ['0', '1'], true];
        yield 'integer' => ['integer', 'int', ['7', '42'], 42];
        yield 'smallint' => ['smallint', 'int', ['7', '42'], 42];
        yield 'bigint' => ['bigint', 'int', ['7', '42'], 42];
        yield 'float' => ['float', 'float', ['1.5', '2.25'], 2.25];
        yield 'decimal' => ['decimal', 'string', ['9.9', '12.5'], '12.50'];
        yield 'string, NULL is no choice' => ['string', 'string', ['123', 'SKU-1'], 'SKU-1'];
        yield 'ascii_string, the empty string is no choice' => ['asciiString', 'string', ['A', 'B'], 'B'];
        yield 'guid' => ['guid', 'string', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12'], 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12'];
        yield 'date' => ['date', \DateTime::class, ['2024-01-01', '2024-02-01'], new \DateTime('2024-02-01')];
        yield 'date as a stringable date' => ['date', \DateTime::class, ['2024-01-01', '2024-02-01'], new StringableDateTime('2024-02-01')];
        yield 'datetime' => ['datetime', \DateTime::class, ['2024-01-01 10:00:00', '2024-02-01 11:30:00'], new \DateTimeImmutable('2024-02-01 11:30:00')];
        yield 'datetimetz, in another offset' => ['datetimetz', \DateTime::class, ['2024-01-01 10:00:00', '2024-02-01 11:30:00'], (new \DateTime('2024-02-01 11:30:00'))->setTimezone(new \DateTimeZone('+02:00'))];
        yield 'time' => ['time', \DateTime::class, ['10:00:00', '11:30:00'], new \DateTime('11:30:00')];
        yield 'date_immutable' => ['dateImmutable', \DateTimeImmutable::class, ['2024-01-01', '2024-02-01'], new \DateTimeImmutable('2024-02-01')];
        yield 'datetime_immutable' => ['datetimeImmutable', \DateTimeImmutable::class, ['2024-01-01 10:00:00', '2024-02-01 11:30:00'], new \DateTimeImmutable('2024-02-01 11:30:00')];
        yield 'datetimetz_immutable' => ['datetimetzImmutable', \DateTimeImmutable::class, ['2024-01-01 10:00:00', '2024-02-01 11:30:00'], new \DateTimeImmutable('2024-02-01 11:30:00')];
        yield 'time_immutable' => ['timeImmutable', \DateTimeImmutable::class, ['10:00:00', '11:30:00'], new \DateTimeImmutable('11:30:00')];
        yield 'dateinterval' => ['dateinterval', \DateInterval::class, ['+P00Y00M00DT02H00M00S', '+P00Y00M01DT00H00M00S'], new \DateInterval('P1D')];
        yield 'uuid' => ['uuid', Uuid::class, ['0190a1f2-0000-7000-8000-000000000001', '0190a1f2-0000-7000-8000-000000000002'], Uuid::fromString('0190a1f2-0000-7000-8000-000000000002')];
        yield 'ulid' => ['ulid', Ulid::class, ['01J0000000000000000000000A', '01J0000000000000000000000B'], Ulid::fromString('01J0000000000000000000000B')];
        yield 'string backed enum' => ['enumString', EnumString::class, ['b', 'f'], EnumString::Foo];
        yield 'int backed enum' => ['enumInt', EnumInt::class, ['0', '1'], EnumInt::Bar];
        yield 'untyped property' => ['untyped', 'int', ['7', '42'], '42'];
        yield 'mixed property' => ['mixed', \DateTime::class, ['2024-01-01', '2024-02-01'], '2024-02-01'];
        yield 'uid in another format' => ['uuidMixed', Uuid::class, ['0190a1f2-0000-7000-8000-000000000001', '0190a1f2-0000-7000-8000-000000000002'], '0190A1F2-0000-7000-8000-000000000002'];
        yield 'union property' => ['integerAsUnion', 'int', ['7', '42'], 42];
        yield 'object property' => ['enumAsObject', EnumString::class, ['b', 'f'], EnumString::Foo];
        yield 'immutable date given as a mutable one' => ['mixedImmutable', \DateTimeImmutable::class, ['2024-01-01', '2024-02-01'], new \DateTime('2024-02-01')];
        yield 'constraint in another group' => ['inAnotherGroup', 'string', ['123', 'SKU-1'], 'SKU-1'];
    }

    public function testDefaultChoiceValuesEnforceTheRepositoryScopeWithoutValidation()
    {
        EntityExistsFieldsRepository::$maxId = 1;
        $form = $this->factory->createBuilder(FormType::class, $dto = new EntityExistsFieldsDto(), ['validation_groups' => false])->add('string')->getForm();
        $form->submit(['string' => '123']);

        $this->assertFalse($form->get('string')->isSynchronized());
        $this->assertNull($dto->string);
    }

    public function testEntityExistsDoesNotGuessChoicesWithoutRegistration()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension($validator))->getFormFactory();
        $form = $factory->createBuilder(FormType::class, new EntityExistsFieldsDto())->add('string')->getForm();

        $this->assertSame(TextType::class, $form->get('string')->getConfig()->getType()->getInnerType()::class);
    }

    #[DataProvider('provideCustomChoiceValues')]
    public function testSubmittedChoiceMustBelongToTheRepositoryScope(bool $render, mixed $choiceValue, string $submitted)
    {
        EntityExistsFieldsRepository::$maxId = 1;
        $form = $this->factory->createBuilder(FormType::class, $dto = new EntityExistsFieldsDto(), ['validation_groups' => false])
            ->add('string', null, ['choice_value' => $choiceValue])
            ->getForm();
        if ($render) {
            $form->createView();
        }
        $form->submit(['string' => $submitted]);

        $this->assertFalse($form->get('string')->isSynchronized());
        $this->assertNull($dto->string);
    }

    public function testGuessWinsOverStandardGuessersInEitherOrder()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $guessers = [new EnumFormTypeGuesser(), new ValidatorTypeGuesser($validator), new DoctrineOrmTypeGuesser($this->registry)];

        foreach ([[$this->guesser, ...$guessers], [...$guessers, $this->guesser]] as $order) {
            $chain = new FormTypeGuesserChain($order);
            foreach (['integer', 'string', 'email', 'enumString'] as $property) {
                $this->assertSame(ChoiceType::class, $chain->guessType(EntityExistsFieldsDto::class, $property)->getType());
            }
        }
    }

    public function testExplicitTypeBypassesTheGuess()
    {
        $form = $this->factory->createBuilder(FormType::class, new EntityExistsFieldsDto())->add('string', TextType::class)->getForm();

        $this->assertSame(TextType::class, $form->get('string')->getConfig()->getType()->getInnerType()::class);
        $this->assertSame([], $this->queries->getData());
    }

    public function testValueReferencingNoEntityIsRejectedByChoiceType()
    {
        $form = $this->createForm('integer', $dto = new EntityExistsFieldsDto());
        $form->submit(['integer' => '999']);

        $this->assertFalse($form->get('integer')->isSynchronized());
        $this->assertNull($dto->integer);
        $this->assertSame('The selected choice is invalid.', $form->get('integer')->getErrors()[0]->getMessage());
    }

    public function testSubmittedValueMustMatchTheChoiceValue()
    {
        $form = $this->createForm('decimal', $dto = new EntityExistsFieldsDto());
        $form->submit(['decimal' => '12.50']);

        $this->assertFalse($form->get('decimal')->isSynchronized());
        $this->assertNull($dto->decimal);
    }

    #[DataProvider('provideTimezoneDates')]
    public function testTimezoneDateChoicesRoundTrip(string $property, string $timezone)
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $form = $this->createForm($property, $dto = new EntityExistsFieldsDto());
            $choice = array_values($form->createView()[$property]->vars['choices'])[1];
            $this->assertSame('2024-02-01 11:30:00', $choice->value);

            $form->submit([$property => $choice->value]);

            $this->assertTrue($form->get($property)->isValid(), (string) $form->get($property)->getErrors());
            $this->assertEquals($choice->data, $dto->$property);
            $this->assertSame($choice->value, $form->createView()[$property]->vars['value']);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public static function provideTimezoneDates(): iterable
    {
        foreach (['datetimetz', 'datetimetzImmutable'] as $property) {
            foreach (['UTC', 'Europe/Paris'] as $timezone) {
                yield $property.' in '.$timezone => [$property, $timezone];
            }
        }
    }

    #[DataProvider('provideTimezoneDatePlatforms')]
    public function testTimezoneDateValuesPreserveTheirInstant(string $typeClass, string $phpType, AbstractPlatform $platform, string $timezone)
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $connection = $this->createStub(Connection::class);
            $connection->method('getDatabasePlatform')->willReturn($platform);
            $manager = $this->createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($connection);
            $type = new $typeClass();
            $loader = new EntityFieldValueChoiceLoader($manager, EntityExistsFieldsEntity::class, 'datetimetz', $type, $phpType);
            $date = new $phpType('2024-02-01 11:30:00');
            $otherOffset = (clone $date)->setTimezone(new \DateTimeZone('+02:00'));
            $value = $loader->getValue($otherOffset);

            $this->assertSame($type->convertToDatabaseValue($date, $platform), $value);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public static function provideTimezoneDatePlatforms(): iterable
    {
        foreach ([DateTimeTzType::class => \DateTime::class, DateTimeTzImmutableType::class => \DateTimeImmutable::class] as $typeClass => $phpType) {
            foreach ([new SQLitePlatform(), new PostgreSQLPlatform()] as $platform) {
                foreach (['UTC', 'Europe/Paris'] as $timezone) {
                    yield $typeClass.' on '.$platform::class.' in '.$timezone => [$typeClass, $phpType, $platform, $timezone];
                }
            }
        }
    }

    public function testFloatChoicesRetainDistinctValues()
    {
        $this->em->getConnection()->executeStatement('UPDATE EntityExistsFieldsEntity SET float = CASE id WHEN 1 THEN 1.2345678901234567 WHEN 2 THEN 1.2345678901234578 ELSE float END');
        $choices = array_values($this->createForm('float')->createView()['float']->vars['choices']);

        $this->assertSame([1.2345678901234567, 1.2345678901234578, 1.5, 2.25], array_map(static fn ($choice) => $choice->data, $choices));
        $this->assertSame(['1.2345678901234567', '1.2345678901234578', '1.5', '2.25'], array_map(static fn ($choice) => $choice->value, $choices));

        foreach ($choices as $choice) {
            $form = $this->createForm('float', $dto = new EntityExistsFieldsDto());
            $form->submit(['float' => $choice->value]);

            $this->assertTrue($form->get('float')->isSynchronized());
            $this->assertSame($choice->data, $dto->float);
        }
    }

    public function testNonFiniteFloatsAreNoChoiceValues()
    {
        $loader = $this->guesser->guessType(EntityExistsFieldsDto::class, 'float')->getOptions()['choice_loader'];

        $this->assertSame('', $loader->getValue(\INF));
        $this->assertSame('', $loader->getValue(-\INF));
        $this->assertSame('', $loader->getValue(\NAN));
    }

    #[DataProvider('provideIntegerBoundaries')]
    public function testIntegerBoundariesRoundTrip(string $submitted, int $expected)
    {
        $this->em->getConnection()->executeStatement('UPDATE EntityExistsFieldsEntity SET integer = ? WHERE id = 1', [$expected]);
        $form = $this->createForm('integer', $dto = new EntityExistsFieldsDto());
        $form->submit(['integer' => $submitted]);

        $this->assertTrue($form->get('integer')->isValid(), (string) $form->get('integer')->getErrors());
        $this->assertSame($expected, $dto->integer);
    }

    public static function provideIntegerBoundaries(): iterable
    {
        yield 'maximum' => [(string) \PHP_INT_MAX, \PHP_INT_MAX];
        yield 'minimum' => [(string) \PHP_INT_MIN, \PHP_INT_MIN];
        yield 'zero' => ['0', 0];
    }

    public function testOverflowCannotValidateAgainstTheMaximumInteger()
    {
        $this->em->getConnection()->executeStatement('UPDATE EntityExistsFieldsEntity SET integer = ? WHERE id = 1', [\PHP_INT_MAX]);
        $form = $this->createForm('integer', $dto = new EntityExistsFieldsDto());
        $form->submit(['integer' => 8 === \PHP_INT_SIZE ? '9223372036854775808' : '2147483648']);

        $this->assertFalse($form->get('integer')->isSynchronized());
        $this->assertSame('The selected choice is invalid.', $form->get('integer')->getErrors()[0]->getMessage());
        $this->assertNull($dto->integer);
    }

    #[DataProvider('provideEqualDecimals')]
    public function testEqualDecimalsSelectTheSameChoice(string $value, string $expected)
    {
        $loader = $this->guesser->guessType(EntityExistsFieldsDto::class, 'decimal')->getOptions()['choice_loader'];

        $this->assertSame($expected, $loader->getValue($value));
    }

    public static function provideEqualDecimals(): iterable
    {
        yield 'leading zeros' => ['009.90', '9.9'];
        yield 'positive sign' => ['+9.90', '9.9'];
        yield 'negative sign' => ['-009.90', '-9.9'];
        yield 'fraction without integer part' => ['.90', '0.9'];
        yield 'negative fraction' => ['-.90', '-0.9'];
        yield 'negative zero' => ['-0.00', '0'];
        yield 'integer without decimal point' => ['0009', '9'];
        yield 'trailing decimal point' => ['9.', '9'];
        yield 'beyond float precision' => ['009007199254740993.0100', '9007199254740993.01'];
    }

    public function testDecimalModelWithLeadingZerosSelectsItsChoice()
    {
        $dto = new EntityExistsFieldsDto();
        $dto->decimal = '+0012.500';

        $this->assertSame('12.5', $this->createForm('decimal', $dto)->createView()['decimal']->vars['value']);
    }

    public function testScientificNotationIsNotAChoiceValue()
    {
        $loader = $this->guesser->guessType(EntityExistsFieldsDto::class, 'decimal')->getOptions()['choice_loader'];

        $this->assertSame('', $loader->getValue('9.9e0'));
    }

    #[DataProvider('provideMalformedValues')]
    public function testMalformedValueIsRejectedByChoiceType(string $property, string $submitted)
    {
        $form = $this->createForm($property, $dto = new EntityExistsFieldsDto());
        $form->submit([$property => $submitted]);

        $this->assertFalse($form->get($property)->isSynchronized());
        $this->assertSame('The selected choice is invalid.', $form->get($property)->getErrors()[0]->getMessage());
        $this->assertNull($dto->$property);
    }

    public static function provideMalformedValues(): iterable
    {
        yield 'integer' => ['integer', 'abc'];
        yield 'integer, not an integer' => ['integer', '42.0'];
        yield 'integer, beyond PHP_INT_MAX' => ['integer', '99999999999999999999'];
        yield 'integer, just beyond PHP_INT_MAX' => ['integer', 8 === \PHP_INT_SIZE ? '9223372036854775808' : '2147483648'];
        yield 'integer, just below PHP_INT_MIN' => ['integer', 8 === \PHP_INT_SIZE ? '-9223372036854775809' : '-2147483649'];
        yield 'float' => ['float', 'abc'];
        yield 'float, positive overflow' => ['float', '1e999'];
        yield 'float, negative overflow' => ['float', '-1e999'];
        yield 'boolean' => ['boolean', 'yes'];
        yield 'decimal' => ['decimal', 'abc'];
        yield 'decimal, scientific notation' => ['decimal', '9.9e0'];
        yield 'decimal, large exponent' => ['decimal', '1e999999999999999999999'];
        yield 'date' => ['date', 'not a date'];
        yield 'uuid' => ['uuid', 'not-a-uuid'];
        yield 'string backed enum' => ['enumString', 'unknown'];
        yield 'int backed enum' => ['enumInt', 'abc'];
        yield 'int backed enum, no case' => ['enumInt', '5'];
    }

    public function testExplicitChoicesReplaceTheValuesOfTheField()
    {
        $form = $this->factory->createBuilder(FormType::class, $dto = new EntityExistsFieldsDto())->add('string', null, ['choices' => ['Only one' => 'SKU-1'], 'choice_loader' => null, 'choice_label' => null])->getForm();
        $choices = array_values($form->createView()['string']->vars['choices']);

        $this->assertSame(['Only one'], array_map(static fn ($choice) => $choice->label, $choices));
        $this->assertSame(['SKU-1'], array_map(static fn ($choice) => $choice->value, $choices));

        $form->submit(['string' => '123']);

        $this->assertFalse($form->get('string')->isSynchronized());
        $this->assertNull($dto->string);
    }

    #[DataProvider('provideCustomChoiceValues')]
    public function testCustomChoiceValueIsMappedBackToItsChoice(bool $render, mixed $choiceValue, string $submitted)
    {
        $form = $this->factory->createBuilder(FormType::class, $dto = new EntityExistsFieldsDto())->add('string', null, ['choice_value' => $choiceValue])->getForm();
        if ($render) {
            $form->createView();
        }
        $form->submit(['string' => $submitted]);

        $this->assertTrue($form->get('string')->isValid(), (string) $form->get('string')->getErrors());
        $this->assertSame('123', $dto->string);
    }

    public static function provideCustomChoiceValues(): iterable
    {
        foreach ([false, true] as $render) {
            yield [$render, static fn ($value) => null === $value ? '' : 'key-'.$value, 'key-123'];
            yield [$render, null, '123'];
            yield [$render, static fn ($value) => (string) $value, '123'];
        }
    }

    public function testLabels()
    {
        $labels = fn (string $property) => array_map(static fn ($choice) => $choice->label, array_values($this->createForm($property)->createView()[$property]->vars['choices']));

        $this->assertSame(['7', '42'], $labels('integer'));
        $this->assertSame(['2024-01-01', '2024-02-01'], $labels('dateImmutable'));
        $this->assertSame(['Bar', 'Foo'], $labels('enumString'));
    }

    public function testRepositoryScopeIsKept()
    {
        EntityExistsFieldsRepository::$maxId = 1;

        $this->assertSame(['SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($this->createForm('string')->createView()['string']->vars['choices'])));
    }

    public function testChoicesFollowTheScopeOfTheQuery()
    {
        $values = fn () => array_map(static fn ($choice) => $choice->value, array_values($this->createForm('string')->createView()['string']->vars['choices']));

        EntityExistsFieldsRepository::$maxId = 1;
        $this->assertSame(['SKU-1'], $values());

        EntityExistsFieldsRepository::$maxId = 2;
        $this->assertSame(['123', 'SKU-1'], $values());

        EntityExistsFieldsRepository::$maxId = 1;
        $this->assertSame(['SKU-1'], $values());
    }

    public function testRepositoryQueryLimitsAreKept()
    {
        $limit = 1;
        $offset = 0;
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturnCallback(function ($alias) use (&$limit, &$offset) {
            return $this->em->createQueryBuilder()->from(EntityExistsFieldsEntity::class, $alias)->setMaxResults($limit)->setFirstResult($offset);
        });
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->method('getConnection')->willReturn($this->em->getConnection());
        $choices = static fn () => array_values((new EntityFieldValueChoiceLoader($manager, EntityExistsFieldsEntity::class, 'string', new StringType(), 'string'))->loadChoiceList()->getChoices());

        $this->assertSame(['123'], $choices());
        $limit = 2;
        $this->assertSame(['123', 'SKU-1'], $choices());
        $limit = 1;
        $offset = 1;
        $this->assertSame(['SKU-1'], $choices());
    }

    public function testRepositoryScopeAppliesAtFirstLoad()
    {
        EntityExistsFieldsRepository::$maxId = 1;
        $form = $this->createForm('string');
        EntityExistsFieldsRepository::$maxId = 2;

        $this->assertSame(['123', 'SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($form->createView()['string']->vars['choices'])));
    }

    public function testChoicesFollowTheEnabledFilters()
    {
        $values = fn () => array_map(static fn ($choice) => $choice->value, array_values($this->createForm('string')->createView()['string']->vars['choices']));

        $this->assertSame(['123', 'SKU-1'], $values());

        $this->em->getConfiguration()->addFilter('tenant', EntityExistsFieldsFilter::class);
        $this->em->getFilters()->enable('tenant')->setParameter('maxId', 1);
        $this->assertSame(['SKU-1'], $values());

        $this->em->getFilters()->disable('tenant');
        $this->assertSame(['123', 'SKU-1'], $values());
    }

    public function testFilterChangesBeforeRenderingApplyToTheQuery()
    {
        $this->em->getConfiguration()->addFilter('tenant', EntityExistsFieldsFilter::class);
        $filter = $this->em->getFilters()->enable('tenant');
        $filter->setParameter('maxId', 1);
        $form = $this->createForm('string');
        $filter->setParameter('maxId', 2);

        $this->assertSame(['123', 'SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($form->createView()['string']->vars['choices'])));
        $filter->setParameter('maxId', 1);
        $this->assertSame(['SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($this->createForm('string')->createView()['string']->vars['choices'])));
        $this->assertCount(2, $this->queries->getData()['default']);
    }

    public function testDisablingFiltersBeforeRenderingAppliesToTheQuery()
    {
        $this->em->getConfiguration()->addFilter('tenant', EntityExistsFieldsFilter::class);
        $this->em->getFilters()->enable('tenant')->setParameter('maxId', 1);
        $form = $this->createForm('string');
        $this->em->getFilters()->disable('tenant');

        $this->assertSame(['123', 'SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($form->createView()['string']->vars['choices'])));
    }

    public function testEnablingFiltersBeforeRenderingAppliesToTheQuery()
    {
        $form = $this->createForm('string');
        $this->em->getConfiguration()->addFilter('tenant', EntityExistsFieldsFilter::class);
        $this->em->getFilters()->enable('tenant')->setParameter('maxId', 1);

        $this->assertSame(['SKU-1'], array_map(static fn ($choice) => $choice->value, array_values($form->createView()['string']->vars['choices'])));
    }

    public function testTearDownWithoutAnEntityManager()
    {
        $this->expectNotToPerformAssertions();
        $this->em->close();
        unset($this->em);
    }

    public function testStoredValuesThatCannotBeConvertedAreNoChoices()
    {
        $this->em->getConnection()->executeStatement("UPDATE EntityExistsFieldsEntity SET enumString = 'legacy', uuid = 'malformed' WHERE id = 5");

        $this->assertSame(['b', 'f'], array_map(static fn ($choice) => $choice->value, array_values($this->createForm('enumString')->createView()['enumString']->vars['choices'])));
        $this->assertCount(2, $this->createForm('uuid')->createView()['uuid']->vars['choices']);
    }

    public function testChoicesAreLoadedOncePerField()
    {
        $form = $this->createForm('integer', $dto = new EntityExistsFieldsDto());
        $this->assertSame([], $this->queries->getData());

        $form->createView();
        $form->createView();
        $this->assertCount(1, $this->queries->getData()['default'] ?? []);
        $this->assertStringContainsString('SELECT DISTINCT', $this->queries->getData()['default'][0]['sql']);

        $this->createForm('integer')->createView();
        $this->assertCount(2, $this->queries->getData()['default']);

        $form->submit(['integer' => '42']);
        $this->assertSame(42, $dto->integer);
        $distinctQueries = array_filter($this->queries->getData()['default'], static fn ($query) => str_contains($query['sql'], 'SELECT DISTINCT'));
        $this->assertCount(2, $distinctQueries);
    }

    public function testNonEmptySubmissionLoadsTheChoices()
    {
        $form = $this->createForm('integer');
        $form->submit(['integer' => '42']);

        $distinctQueries = array_filter($this->queries->getData()['default'], static fn ($query) => str_contains($query['sql'], 'SELECT DISTINCT'));
        $this->assertCount(1, $distinctQueries);
        $this->assertTrue($form->get('integer')->isValid());
    }

    public function testGuessingDoesNotConnect()
    {
        $connection = $this->em->getConnection();
        $connection->close();

        $this->createForm('date', new EntityExistsFieldsDto());

        $this->assertFalse($connection->isConnected());
    }

    #[DataProvider('provideNotGuessedProperties')]
    public function testNoGuess(string $class, string $property)
    {
        $this->assertNull($this->guesser->guessType($class, $property));
    }

    public static function provideNotGuessedProperties(): iterable
    {
        $dto = EntityExistsFieldsDto::class;
        $child = EntityExistsFieldsChildDto::class;

        yield 'lookup by the identifier' => [$dto, 'identifier'];
        yield 'explicit lookup by the identifier' => [$dto, 'explicitIdentifier'];
        yield 'repository method' => [$dto, 'repositoryMethod'];
        yield 'simple_array, an array choice is a group' => [$dto, 'simpleArray'];
        yield 'json' => [$dto, 'json'];
        yield 'text, a LOB on some platforms' => [$dto, 'text'];
        yield 'enum stored in an array' => [$dto, 'enumArray'];
        yield 'association' => [$dto, 'association'];
        yield 'unknown field' => [$dto, 'unknownField'];
        yield 'unknown entity' => [$dto, 'unknownEntity'];
        yield 'repeated constraint' => [$dto, 'repeated'];
        yield 'property refusing null' => [$dto, 'requiredInteger'];
        yield 'date written to a string' => [$dto, 'dateAsString'];
        yield 'string written to an int' => [$dto, 'stringAsInt'];
        yield 'enum written to a string' => [$dto, 'enumAsString'];
        yield 'mutable date written to an immutable one' => [$dto, 'mutableDateAsImmutable'];
        yield 'integer written to a float' => [$dto, 'integerAsFloat'];
        yield 'integer written to a string' => [$dto, 'integerAsString'];
        yield 'uuid written to a string' => [$dto, 'uuidAsString'];
        yield 'no constraint' => [$dto, 'unconstrained'];
        yield 'unknown property' => [$dto, 'unknownProperty'];
        yield 'enum written through a string setter of the parent class' => [$child, 'enumThroughStringSetter'];
        yield 'setter refusing null' => [$child, 'stringThroughNonNullableSetter'];
        yield 'date written through a string setter to an untyped property' => [$child, 'untypedDateThroughStringSetter'];
        yield 'not writable' => [$child, 'notWritable'];
        yield 'protected setter' => [$child, 'protectedSetter'];
    }

    public function testGuessForASetHookAcceptingTheValues()
    {
        $this->assertSame(ChoiceType::class, $this->guesser->guessType(EntityExistsFieldsDto::class, 'dateThroughHook')?->getType());

        $form = $this->createForm('dateThroughHook', $dto = new EntityExistsFieldsDto());
        $form->submit(['dateThroughHook' => '2024-02-01']);

        $this->assertSame('2024-02-01', $dto->dateThroughHook);
    }

    public function testRequiredFieldKeepsAnEmptyChoice()
    {
        $view = $this->factory->createBuilder(FormType::class, new EntityExistsFieldsDto())->add('string', null, ['required' => true])->getForm()->createView()['string'];

        $this->assertSame('', $view->vars['placeholder']);
    }

    public function testDecimalsAreComparedByValue()
    {
        $loader = new EntityFieldValueChoiceLoader($this->em, EntityExistsFieldsEntity::class, 'decimal', new DecimalType(), 'string');

        $this->assertSame('0', $loader->getValue('.00'));
        $this->assertSame('0.5', $loader->getValue('.50'));
        $this->assertSame('-0.5', $loader->getValue('-.50'));
        $this->assertSame('12.5', $loader->getValue('12.50'));
        $this->assertSame('10', $loader->getValue('10'));
        $this->assertSame('10', $loader->getValue('10.00'));
    }

    public function testStoredValuesOfAnotherPhpTypeAreNoChoices()
    {
        $type = new class extends BigIntType {
            public function convertToPHPValue(mixed $value, AbstractPlatform $platform): string
            {
                return '18446744073709551615';
            }
        };

        $loader = new EntityFieldValueChoiceLoader($this->em, EntityExistsFieldsEntity::class, 'bigint', $type, 'int');

        $this->assertSame([], $loader->loadChoiceList()->getChoices());
    }

    public function testGuessForASetterOfTheParentClass()
    {
        $this->assertSame(ChoiceType::class, $this->guesser->guessType(EntityExistsFieldsChildDto::class, 'stringThroughSetter')?->getType());
    }

    public function testNoGuessWhenTheRegistryCannotReflectTheEntity()
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willThrowException(new \ReflectionException('Class "DoesNotExist" does not exist'));
        $guesser = new EntityExistsTypeGuesser($registry, new LazyLoadingMetadataFactory(new AttributeLoader()));

        $this->assertNull($guesser->guessType(EntityExistsFieldsDto::class, 'string'));
    }

    public function testNoGuessWithoutAnEntityManager()
    {
        $guesser = new EntityExistsTypeGuesser($this->createRegistry(null), new LazyLoadingMetadataFactory(new AttributeLoader()));

        $this->assertNull($guesser->guessType(EntityExistsFieldsDto::class, 'string'));
    }

    public function testNoGuessForAnotherObjectManager()
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('hasField')->willReturn(true);
        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $guesser = new EntityExistsTypeGuesser($registry, new LazyLoadingMetadataFactory(new AttributeLoader()));

        $this->assertNull($guesser->guessType(EntityExistsFieldsDto::class, 'string'));
    }

    public function testNoGuessForUidColumnsOfAnotherType()
    {
        DoctrineTestHelper::registerTypes($this->em->getConfiguration(), ['uuid' => GuidType::class]);

        try {
            $this->assertNull((new EntityExistsTypeGuesser($this->registry, new LazyLoadingMetadataFactory(new AttributeLoader())))->guessType(EntityExistsFieldsDto::class, 'uuid'));
        } finally {
            DoctrineTestHelper::registerTypes($this->em->getConfiguration(), ['uuid' => UuidType::class]);
        }
    }

    public function testNoOtherGuess()
    {
        $this->assertNull($this->guesser->guessRequired(EntityExistsFieldsDto::class, 'string'));
        $this->assertNull($this->guesser->guessMaxLength(EntityExistsFieldsDto::class, 'string'));
        $this->assertNull($this->guesser->guessPattern(EntityExistsFieldsDto::class, 'string'));
    }

    private function createForm(string $property, ?EntityExistsFieldsDto $dto = null): FormInterface
    {
        return $this->factory->createBuilder(FormType::class, $dto ?? new EntityExistsFieldsDto())->add($property)->getForm();
    }

    private function createRegistry(?EntityManager $em): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManagers')->willReturn($em ? ['default' => $em] : []);
        if ($em) {
            $registry->method('getManager')->willReturn($em);
        }

        return $registry;
    }

    private static function createEntity(int $id, int $k): EntityExistsFieldsEntity
    {
        $entity = new EntityExistsFieldsEntity();
        $entity->id = $id;
        $entity->boolean = [false, true][$k];
        $entity->integer = $entity->smallint = $entity->bigint = [7, 42][$k];
        $entity->float = [1.5, 2.25][$k];
        $entity->decimal = ['9.90', '12.50'][$k];
        $entity->string = ['SKU-1', '123'][$k];
        $entity->asciiString = ['A', 'B'][$k];
        $entity->text = ['long a', 'long b'][$k];
        $entity->guid = ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12'][$k];
        $entity->date = new \DateTime(['2024-01-01', '2024-02-01'][$k]);
        $entity->datetime = new \DateTime(['2024-01-01 10:00:00', '2024-02-01 11:30:00'][$k]);
        $entity->datetimetz = new \DateTime(['2024-01-01 10:00:00+00:00', '2024-02-01 11:30:00+00:00'][$k]);
        $entity->time = new \DateTime(['10:00:00', '11:30:00'][$k]);
        $entity->dateImmutable = new \DateTimeImmutable(['2024-01-01', '2024-02-01'][$k]);
        $entity->datetimeImmutable = new \DateTimeImmutable(['2024-01-01 10:00:00', '2024-02-01 11:30:00'][$k]);
        $entity->datetimetzImmutable = new \DateTimeImmutable(['2024-01-01 10:00:00+00:00', '2024-02-01 11:30:00+00:00'][$k]);
        $entity->timeImmutable = new \DateTimeImmutable(['10:00:00', '11:30:00'][$k]);
        $entity->dateinterval = new \DateInterval(['PT2H', 'P1D'][$k]);
        $entity->uuid = Uuid::fromString(['0190a1f2-0000-7000-8000-000000000001', '0190a1f2-0000-7000-8000-000000000002'][$k]);
        $entity->ulid = Ulid::fromString(['01J0000000000000000000000A', '01J0000000000000000000000B'][$k]);
        $entity->enumString = [EnumString::Bar, EnumString::Foo][$k];
        $entity->enumInt = [EnumInt::Foo, EnumInt::Bar][$k];
        $entity->simpleArray = [['a', 'b'], ['c']][$k];
        $entity->enumArray = [[EnumString::Bar], [EnumString::Foo]][$k];
        $entity->json = [['a'], ['b']][$k];

        return $entity;
    }
}
