<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

require_once __DIR__.'/EntityParent.php';
require_once __DIR__.'/EntityInterface.php';

/**
 * @Symfony\Tests\Component\Validator\Fixtures\ConstraintA
 * @assert:GroupSequence({"Foo", "Entity"})
 */
class Entity extends EntityParent implements EntityInterface
{
    /**
     * @assert:NotNull
     * @assert:Min(3)
     * @assert:Set({
     *   @assert:All({@assert:NotNull, @assert:Min(3)}),
     *   @assert:All(constraints={@assert:NotNull, @assert:Min(3)})
     * })
     * @assert:Collection(fields={
     *   "foo" = {@assert:NotNull, @assert:Min(3)},
     *   "bar" = @assert:Min(5)
     * })
     * @assert:Choice(choices={"A", "B"}, message="Must be one of %choices%")
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
     * @assert:NotNull
     */
    public function getLastName()
    {
        return $this->lastName;
    }
}
