CHANGELOG
=========

3.4.0
-----

 * added `AbstractCloner::setMinDepth()` function to ensure minimum tree depth
 * added `HtmlDumper::markHeaderAsDumped()` function to prevent dumping HTML header
 * increased visibility of `HtmlDumper::getDumpHeader()` function from protected to public

2.7.0
-----

 * deprecated Cloner\Data::getLimitedClone(). Use withMaxDepth, withMaxItemsPerDepth or withRefHandles instead.
