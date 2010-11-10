<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\DoctrineBundle\Form\ValueTransformer\EntityToIDTransformer;

class EntityToIDTransformerTest extends \Symfony\Bundle\DoctrineBundle\Tests\TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    public function setUp()
    {
        parent::setUp();
        $this->em = $this->createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array($this->em->getClassMetadata('Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag'));
        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {

        }
        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
            
        }
    }

    public function testRequiredEntityManager()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new EntityToIDTransformer(array('className' => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag'));
    }

    public function testRequiredClassName()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new EntityToIDTransformer(array('em' => $this->em));
    }

    public function createTransformer()
    {
        $transformer = new EntityToIDTransformer(array(
            'em' => $this->em,
            'className' => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag'
        ));
        return $transformer;
    }

    public function testTranformEmptyValueReturnsNull()
    {
        $transformer = $this->createTransformer();
        $this->assertEquals(0, $transformer->transform(null));
        $this->assertEquals(0, $transformer->transform(""));
        $this->assertEquals(0, $transformer->transform(0));
    }

    public function testTransform()
    {
        $transformer = $this->createTransformer();

        $tag = new Tag("name");
        $this->em->persist($tag);
        $this->em->flush();

        $this->assertEquals(1, $transformer->transform($tag));
    }

    public function testReverseTransformEmptyValue()
    {
        $transformer = $this->createTransformer();
        $this->assertNull($transformer->reverseTransform(0, null));
    }

    public function testReverseTransform()
    {
        $transformer = $this->createTransformer();

        $tag = new Tag("name");
        $this->em->persist($tag);
        $this->em->flush();

        $this->assertSame($tag, $transformer->reverseTransform(1, null));
    }
}