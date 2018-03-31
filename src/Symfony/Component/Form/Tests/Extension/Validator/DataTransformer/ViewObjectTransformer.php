<?php
namespace Symfony\Component\Form\Tests\Extension\Validator\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
* Transforms Object to array for view
*
* @author Charles J. C. Elling <charles@denumeris.com>
*/
class ViewObjectTransformer implements DataTransformerInterface {
	
	/**
	 * Expected class of object
	 * @var string $dataClass
	 **/
	protected $dataClass;

	public function __construct($dataClass) {
		$this->dataClass = $dataClass;
	}

	public function transform($object) {
		if($object === null) {
			return null;
		}else if(is_a($object, $this->dataClass)) {
			return (array)$object;
		}else {
			if(is_object($object)) {
				$type = get_class($object);
			}else {
				$type = gettype($object);
			}
			throw new TransformationFailedException("Expected an {$this->dataClass} got {$type}");
		}
	}

	public function reverseTransform($viewData) {
		if ($viewData === null) {
			$viewData = array();
		}
		if(is_array($viewData) || $viewData instanceof \Traversable) {
			$dataClass = $this->dataClass;
			$object = new $dataClass();
			foreach ($viewData as $name => $value) {
				$object->{$name} = $value;
			}
			return $object;
		} else {
			if (is_object($viewData)) {
				$type = get_class($viewData);
			} else {
				$type = gettype($viewData);
			}
			throw new TransformationFailedException("Expected an array got {$type}");
		}
	}
}