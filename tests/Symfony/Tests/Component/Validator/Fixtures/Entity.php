<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

require_once __DIR__.'/EntityParent.php';
require_once __DIR__.'/EntityInterface.php';

/**
 * @Symfony\Tests\Component\Validator\Fixtures\ConstraintA
 * @validation:GroupSequence({"Foo", "Entity"})
 */
class Entity extends EntityParent implements EntityInterface
{
    /**
     * @validation:NotNull
     * @validation:Min(3)
     * @validation:Set({
     *   @validation:All({@validation:NotNull, @validation:Min(3)}),
     *   @validation:All(constraints={@validation:NotNull, @validation:Min(3)})
     * })
     * @validation:Collection(fields={
     *   "foo" = {@validation:NotNull, @validation:Min(3)},
     *   "bar" = @validation:Min(5)
     * })
     * @validation:Choice(choices={"A", "B"}, message="Must be one of %choices%")
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
     * @validation:NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }
}
