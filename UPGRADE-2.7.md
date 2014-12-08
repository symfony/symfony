UPGRADE FROM 2.6 to 2.7
=======================

Form
----

 * In form types and extension overriding the "setDefaultOptions" of the
   AbstractType or AbstractExtensionType has been deprecated in favor of
   overriding the new "configureOptions" method.

   The method "setDefaultOptions(OptionsResolverInterface $resolver)" will 
   be renamed in Symfony 3.0 to "configureOptions(OptionsResolver $resolver)".

   Before:

   ```php
        use Symfony\Component\OptionsResolver\OptionsResolverInterface;
        
        class TaskType extends AbstractType
        {
            // ...
            public function setDefaultOptions(OptionsResolverInterface $resolver)
            {
                $resolver->setDefaults(array(
                    'data_class' => 'AppBundle\Entity\Task',
                ));
            }
        }
   ```

   After:

   ```php
        use Symfony\Component\OptionsResolver\OptionsResolver;
        
        class TaskType extends AbstractType
        {
            // ...
            public function configureOptions(OptionsResolver $resolver)
            {
                $resolver->setDefaults(array(
                    'data_class' => 'AppBundle\Entity\Task',
                ));
            }
        }
   ```
