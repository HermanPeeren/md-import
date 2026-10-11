<?php

/**
 * What PHPStan needs to know that only a running Joomla defines.
 *
 * The Joomla constants this component reads, which `includes/defines.php`
 * defines when an application boots; and league/commonmark, which on a site
 * comes from lib_yepr_commonmark and here from that library's vendor tree.
 */

declare(strict_types=1);

\define('_JEXEC', 1);
\define('JPATH_ROOT', '');
\define('JPATH_BASE', '');
// The repository's library folder mirrors a site's libraries/ folder.
\define('JPATH_LIBRARIES', __DIR__ . '/../src/library');
\define('JVERSION', '6.1.3');

require_once __DIR__ . '/../src/library/yepr_commonmark/vendor/autoload.php';
