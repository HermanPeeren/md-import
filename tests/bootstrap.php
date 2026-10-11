<?php

/**
 * What the suite needs before it can load any of the component's own classes.
 *
 * A Joomla source file opens with `defined('_JEXEC') or die;`. Under PHPUnit
 * that constant is not defined, so loading such a class would call `die` and end
 * the run in silence. Defining it is what Joomla's own test suites do.
 *
 * CommonMark is loaded from the library this package ships, not from a copy of
 * our own: the tests then convert with exactly the code a site will run.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
require __DIR__ . '/../vendor/autoload.php';

$library = __DIR__ . '/../src/library/yepr_commonmark/vendor/autoload.php';

if (!is_file($library)) {
    fwrite(STDERR, "The CommonMark library is not installed. Run `composer install` at the repository root.\n");
    exit(1);
}

require $library;

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}
// phpcs:enable PSR1.Files.SideEffects
