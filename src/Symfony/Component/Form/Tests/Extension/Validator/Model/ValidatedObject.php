<?php
namespace Symfony\Component\Form\Tests\Extension\Validator\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Test object for validation
 * @author Charles J. C. Elling <charles@denumeris.com>
 **/
class ValidatedObject{

	/**
	 * @var string $property1
	 * @Assert\NotBlank()
	 **/
	public $property1;

	/**
	 * @var string $property2
	 * @Assert\NotBlank()
	 **/
	public $property2;

	/**
	 * @var ValidatedObject
	 * @Assert\Valid()
	 **/
	public $childObject;

	public function __construct(ValidatedObject $childObject = null){
		$this->childObject = $childObject;
	}

}