<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorBag;

class FormErrorBagTest extends \Symfony\Component\Form\Tests\FormPerformanceTestCase
{
    public function testIterateErrors()
    {
        $collection = new FormErrorBag(true);
        $collection->addCollection('date', new FormErrorBag());
        $collection->addCollection('checkbox', new FormErrorBag());
        $collection->addError(new FormError('This value should not be blank.'));

        $this->assertCount(1, $collection);
        foreach ($collection as $error) {
            $this->assertInstanceof('Symfony\Component\Form\FormError', $error);
            $this->assertEquals('This value should not be blank.', $error->getMessage());
        }
    }

    public function testRecursivelyIterateErrors()
    {
        $collection = new FormErrorBag();
        $collection->addError(new FormError('This value should not be blank.'));

        $childrenCollection = new FormErrorBag();
        $childrenCollection->addError(new FormError('This value is not a valid email.'));
        $childrenCollection->addError(new FormError('This value is not a valid date.'));
        $collection->addCollection('user', $childrenCollection);

        $iterator = new \RecursiveIteratorIterator($collection);
        $messages = array(
            '', // because we use next() in the loop
            'This value should not be blank.',
            'This value is not a valid email.',
            'This value is not a valid date.',
        );
        foreach ($iterator as $error) {
            $this->assertInstanceof('Symfony\Component\Form\FormError', $error);
            $this->assertEquals(next($messages), $error->getMessage());
        }
    }

    public function testCountingErrors()
    {
        $collection = new FormErrorBag();
        $collection->addError(new FormError('This value should not be blank.'));
        $collection->addError(new FormError('This value should not be blank.'));

        $childrenCollection = new FormErrorBag();
        $childrenCollection->addError(new FormError('This value is not a valid email.'));
        $childrenCollection->addError(new FormError('This value is not a valid date.'));
        $collection->addCollection('user', $childrenCollection);

        $this->assertCount(2, $collection);
    }

    public function testCoutingAllErrors()
    {
        $collection = new FormErrorBag();
        $collection->addError(new FormError('This value should not be blank.'));

        $childrenCollection = new FormErrorBag();
        $childrenCollection->addError(new FormError('This value is not a valid email.'));
        $childrenCollection->addError(new FormError('This value is not a valid date.'));
        $collection->addCollection('user', $childrenCollection);

        $this->assertEquals(3, $collection->countAll());
    }

    public function testFormNameAsKeys()
    {
        $collection = new FormErrorBag();
        $collection->addError(new FormError('This value should not be blank.'));

        $childrenCollection = new FormErrorBag();
        $childrenCollection->addError(new FormError('This value is not a valid email.'));
        $childrenCollection->addError(new FormError('This value is not a valid date.'));
        $collection->addCollection('user', $childrenCollection);

        $iterator = new \RecursiveIteratorIterator($collection);
        $keys = array(
            '', // use of next() in loop
            '0',
            'user',
            'user',
        );
        foreach ($iterator as $name => $error) {
            $this->assertEquals(next($keys), $name);
        }
    }

    public function testToString()
    {
        $collection = new FormErrorBag();
        $collection->addError(new FormError('This value should not be blank.'));

        $childrenCollection = new FormErrorBag();
        $childrenCollection->addError(new FormError('This value is not a valid email.'));
        $childrenCollection->addError(new FormError('This value is not a valid date.'));
        $collection->addCollection('user', $childrenCollection);

        $collection->addCollection('date', new FormErrorBag());

        $this->assertEquals("ERROR: This value should not be blank.\nuser:\n    ERROR: This value is not a valid email.\n    ERROR: This value is not a valid date.\ndate:\n    No errors\n", (string) $collection);
    }
}
