PhpSyntax
=========

**A lossless concrete syntax tree (CST) for PHP.** Every token, every space and every comment of the
source is in the tree, and printing the tree gives the file back byte for byte. Change one thing and the
diff contains one thing.

**Status: in development.** Until the first release the API, names and behavior may still change.

 <!---->

Credits
-------

- The PHP grammar (`grammar/php.y`) and the token emulators come from [nikic/php-parser](https://github.com/nikic/PHP-Parser), BSD-3-Clause.
- The parser build pipeline comes from [Latte](https://github.com/nette/latte).
