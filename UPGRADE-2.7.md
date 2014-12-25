UPGRADE FROM 2.6 to 2.7
=======================

Router
------

 * Route conditions now support container parameters which
   can be injected into condition using `%parameter%` notation.
   Due to the fact that it works by replacing all parameters
   with their corresponding values before passing condition
   expression for compilation there can be BC breaks where you
   could already have used percentage symbols. Single percentage symbol
   usage is not affected in any way. Conflicts may occur where
   you might have used `%` as a modulo operator, here's an example:
   `foo%bar%2` which would be compiled to `$foo % $bar % 2` in 2.6
   but in 2.7 you would get an error if `bar` parameter
   doesn't exist or unexpected result otherwise.
 
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

Serializer
----------

 * The `setCamelizedAttributes()` method of the
   `Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` classes is marked
   as deprecated in favor of the new NameConverter system.

   Before:

   ```php
   $normalizer->setCamelizedAttributes(array('foo_bar', 'bar_foo'));
   ```

   After:

   ```php
   use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
   use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

   $nameConverter = new CamelCaseToSnakeCaseNameConverter(array('fooBar', 'barFoo'));
   $normalizer = new GetSetMethodNormalizer(null, $nameConverter);
   ```
