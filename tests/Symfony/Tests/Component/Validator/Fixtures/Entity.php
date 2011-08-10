<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

require_once __DIR__.'/EntityParent.php';
require_once __DIR__.'/EntityInterface.php';

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Symfony\Tests\Component\Validator\Fixtures\ConstraintA
 * @Assert\GroupSequence({"Foo", "Entity"})
 */
class Entity extends EntityParent implements EntityInterface
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
}
