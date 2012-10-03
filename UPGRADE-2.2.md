UPGRADE FROM 2.1 to 2.2
=======================

### Routing

  * Added possibility to generate URLs that include the default param, whereas before a param
    that equals the default value of an optional placeholder was never part of the URL.
    Example: Given the route `new Route('/index.{_format}', array('_format' => 'html'));`
    Previously generating this route with params `array('_format' => 'html')` resulted in `/index` 
    which means one could not generate `/index.html` at all. This has been changed. You can of course
    still generate just `/index` by passing array('_format' => null) or not passing the param at all.
