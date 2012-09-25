CHANGELOG
=========

2.1.0
-----

 * added Finder::sortByAccessedTime(), Finder::sortByChangedTime(), and
   Finder::sortByModifiedTime()
 * added Countable to Finder
 * added support for an array of directories as an argument to
   Finder::exclude()
 * added searching based on the file content via Finder::contains() and
   Finder::notContains()
 * added support for the != operator in the Comparator
 * [BC BREAK] filter expressions (used for file name and content) are no more
   considered as regexps but glob patterns when they are enclosed in '*' or '?'
