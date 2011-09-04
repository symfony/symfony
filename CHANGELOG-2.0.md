CHANGELOG for 2.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.0 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.0.0...v2.0.1

* 2.0.1 (2011-08-26)

   * 1c7694f: [HttpFoundation] added a missing exception
   * 84c1719: [FrameworkBundle] Avoid listener key conflicts in ContainerAwareEventDispatcher
   * 536538f: [DoctrineBundle] removed an unused and confusing parameter (the connection class can be changed via the wrapper_class setting of a connection)
   * d7f0789: [FrameworkBundle] fixed duplicated RequestContext instances
   * 89f477e: [WebProfilerBundle] Throw exception if a collector template isn't found
   * 6ca72cf: [WebProfilerBundle] Allow .html.twig in collector template names
   * 39fabab: [EventDispatcher] Fix removeSubscriber() to work with priority syntax
   * 3380f2a: [DomCrawler] fixed disabled fields in forms (they are available in the DOM, but their values are not submitted -- whereas before, they were simply removed from the DOM)
   * 2b1bb2c: [Form] added missing DelegatingValidator registration in the Form Extension class (used when using the Form component outside a Symfony2 project where the validation.xml is used instead)
   * fdd2e7a: [Form] Fixing a bug where setting empty_value to false caused a variable to not be found
   * bc7edfe: [FrameworkBundle] changed resource filename of Japanese validator translation
   * c29fa9d: [Form] Fix for treatment zero as empty data. Closes #1986
   * 6e7c375: [FrameworkBundle] Cleanup schema file
   * b6ee1a6: fixes a bug when overriding method via the X-HTTP-METHOD-OVERRIDE header
   * 80d1718: [Fix] Email() constraints now guess as 'email' field type
   * 3a64b08: Search in others user providers when a user is not found in the first user provider and throws the right exception.
   * 805a267: Remove Content-Length header adding for now. Fixes #1846.
   * ae55a98: Added $format in serialize() method, to keep consistence and give a hint to the normalizer.
   * 7ec533e: got an if-condition out of unnecessary loops in Symfony\Component\ClassLoader\UniversalClassLoader
   * 34a1b53: [HttpFoundation] Do not save session in Session::__destroy() when saved already
   * 81fb8e1: [DomCrawler] fix finding charset in addContent
   * 4f9d229: The trace argument value could be string ("*DEEP NESTED ARRAY*")
   * be031f5: [HttpKernel] fixed ControllerResolver when the controller is a class name with an __invoke() method
   * 275da0d: [Validator] changed 'self' to 'static' for child class to override pattern constant
   * e78bc32: Fixed: Notice: Undefined index: enable_annotations in ...
   * 86f888f: fix https default port check
   * 8a980bd: $node->hasAttribute('disabled') sf2 should not create disagreement between implementation and practice for a crawler. If sahi real browser can find an element that is disabled, then sf2 should too. https://github.com/Behat/Mink/pull/58#issuecomment-1712459
   * 1087792: -- fix use of STDIN
   * ee5b9ce: [SwiftmailerBundle] Allow non-file spools
   * d880db2: [Form] Test covered fix for invalid date (13 month/31.02.2011 etc.) send to transformer. Closes #1755
   * df74f49: Patched src/Symfony/Component/Form/Extension/Core/DataTransformer/DateTimeToArrayTransformer.php to throw an exception when an invalid date is passed for transformation (e.g. 31st February)
   * 8519967: Calling supportsClass from vote to find out if we can vote

* 2.0.0 (2011-07-28)
