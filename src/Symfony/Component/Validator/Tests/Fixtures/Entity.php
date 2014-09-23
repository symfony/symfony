<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * @Symfony\Component\Validator\Tests\Fixtures\ConstraintA
 * @Assert\GroupSequence({"Foo", "Entity"})
 * @Assert\Callback({"Symfony\Component\Validator\Tests\Fixtures\CallbackClass", "callback"})
 */
class Entity extends EntityParent implements EntityInterface
{
    /**
     * @Assert\NotNull
     * @Assert\Range(min=3)
     * @Assert\All({@Assert\NotNull, @Assert\Range(min=3)}),
     * @Assert\All(constraints={@Assert\NotNull, @Assert\Range(min=3)})
     * @Assert\Collection(fields={
     *   "foo" = {@Assert\NotNull, @Assert\Range(min=3)},
     *   "bar" = @Assert\Range(min=5)
     * })
     * @Assert\Choice(choices={"A", "B"}, message="Must be one of %choices%")
     */
    public $firstName;
    protected $lastName;
    public $reference;
    public $reference2;
    private $internal;
    public $data = 'Overridden data';
    public $initialized = false;

    public function __construct($internal = null)
    {
        $this->internal = $internal;
    }

    public function getInternal()
    {
        return $this->internal.' from getter';
    }

    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * @Assert\NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @Assert\True
     */
    public function isValid()
    {
        return 'valid';
    }

    /**
     * @Assert\True
     */
    public function hasPermissions()
    {
        return 'permissions';
    }

    public function getData()
    {
        return 'Overridden data';
    }

    /**
     * @Assert\Callback
     */
    public function validateMe(ExecutionContextInterface $context)
    {
    }

    /**
     * @Assert\Callback
     */
    public static function validateMeStatic($object, ExecutionContextInterface $context)
    {
    }
}
