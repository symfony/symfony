<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Form\CacheWarmer\TypeInfoFormTypeGuesserCacheWarmer;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\TypeInfoFormTypeGuesserCase;
use Symfony\Component\Form\Tests\Fixtures\TypeInfoFormTypeGuesserChildCase;
use Symfony\Component\Form\TypeInfoFormTypeGuesser;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

class TypeInfoFormTypeGuesserCacheWarmerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sf_type_info_form_type_guesser_cache_warmer';
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/form_type_guesser.php');
        @rmdir($this->dir);
    }

    public function testGuessesAreReadFromTheWarmedFile()
    {
        $warmer = new TypeInfoFormTypeGuesserCacheWarmer([
            TypeInfoFormTypeGuesserCase::class => ['nullableInt', 'dateTime', 'string', 'undefined'],
            TypeInfoFormTypeGuesserChildCase::class => ['privateInt'],
            'Symfony\Component\Form\Tests\Fixtures\Missing' => ['property'],
        ], $this->dir.'/form_type_guesser.php');
        $warmer->warmUp($this->dir, $this->dir);

        $guesser = new TypeInfoFormTypeGuesser($this->createUnusedTypeResolver(), PhpArrayAdapter::create($this->dir.'/form_type_guesser.php', $fallbackPool = new ArrayAdapter()));

        $this->assertEquals(new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE), $guesser->guessType(TypeInfoFormTypeGuesserCase::class, 'nullableInt'));
        $this->assertEquals(new ValueGuess(false, Guess::LOW_CONFIDENCE), $guesser->guessRequired(TypeInfoFormTypeGuesserCase::class, 'nullableInt'));
        $this->assertEquals(new TypeGuess(DateTimeType::class, ['input' => 'datetime'], Guess::MEDIUM_CONFIDENCE), $guesser->guessType(TypeInfoFormTypeGuesserCase::class, 'dateTime'));
        $this->assertNull($guesser->guessRequired(TypeInfoFormTypeGuesserCase::class, 'dateTime'));
        $this->assertNull($guesser->guessType(TypeInfoFormTypeGuesserCase::class, 'string'));
        $this->assertNull($guesser->guessType(TypeInfoFormTypeGuesserCase::class, 'undefined'));
        $this->assertEquals(new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE), $guesser->guessType(TypeInfoFormTypeGuesserChildCase::class, 'privateInt'));
        $this->assertNull($guesser->guessType('Symfony\Component\Form\Tests\Fixtures\Missing', 'property'));
        $this->assertSame([], $fallbackPool->getValues());
    }

    public function testFieldsThatAreNotWarmedAreCachedInTheFallbackPool()
    {
        $warmer = new TypeInfoFormTypeGuesserCacheWarmer([TypeInfoFormTypeGuesserCase::class => ['nullableInt']], $this->dir.'/form_type_guesser.php');
        $warmer->warmUp($this->dir, $this->dir);

        $fallbackPool = new ArrayAdapter();
        (new TypeInfoFormTypeGuesser(null, PhpArrayAdapter::create($this->dir.'/form_type_guesser.php', $fallbackPool)))->guessType(TypeInfoFormTypeGuesserCase::class, 'float');

        $this->assertCount(1, $fallbackPool->getValues());

        $guesser = new TypeInfoFormTypeGuesser($this->createUnusedTypeResolver(), PhpArrayAdapter::create($this->dir.'/form_type_guesser.php', $fallbackPool));

        $this->assertEquals(new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE), $guesser->guessType(TypeInfoFormTypeGuesserCase::class, 'float'));
    }

    public function testNothingIsWarmedWithoutBuildDir()
    {
        $warmer = new TypeInfoFormTypeGuesserCacheWarmer([TypeInfoFormTypeGuesserCase::class => ['nullableInt']], $this->dir.'/form_type_guesser.php');

        $this->assertSame([], $warmer->warmUp($this->dir));
        $this->assertFileDoesNotExist($this->dir.'/form_type_guesser.php');
    }

    private function createUnusedTypeResolver(): TypeResolverInterface
    {
        $typeResolver = $this->createMock(TypeResolverInterface::class);
        $typeResolver->expects($this->never())->method('resolve');

        return $typeResolver;
    }
}
