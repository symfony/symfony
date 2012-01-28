<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

require_once __DIR__.'/EntityParent.php';
require_once __DIR__.'/EntityInterface.php';

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * @Symfony\Tests\Component\Validator\Fixtures\ConstraintA
 * @Assert\GroupSequence({"Foo", "Entity"})
 * @Assert\GroupSequenceProvider("Symfony\Tests\Component\Validator\Fixtures\GroupSequenceProvider")
 */
class Entity extends EntityParent implements EntityInterface, GroupSequenceProviderInterface
{
    /**
     * @Assert\NotNull
     * @Assert\Min(3)
     * @Assert\All({@Assert\NotNull, @Assert\Min(3)}),
     * @Assert\All(constraints={@Assert\NotNull, @Assert\Min(3)})
     * @Assert\Collection(fields={
     *   "foo" = {@Assert\NotNull, @Assert\Min(3)},
     *   "bar" = @Assert\Min(5)
     * })
     * @Assert\Choice(choices={"A", "B"}, message="Must be one of %choices%")
     */
    protected $firstName;
    protected $lastName;
    public $reference;

    protected $groups = array();

    private $internal;

    public function __construct($internal = null)
    {
        $this->internal = $internal;
    }

    public function getInternal()
    {
        return $this->internal . ' from getter';
    }

    /**
     * @Assert\NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    public function setGroups($groups)
    {
        $this->groups = $groups;
    }

    public function getValidationGroups()
    {
        return $this->groups;
    }
}
