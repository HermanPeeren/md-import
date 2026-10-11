<?php

/**
 * @package     Yepr CommonMark library
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

/**
 * Makes league/commonmark available.
 *
 *   require_once JPATH_LIBRARIES . '/yepr_commonmark/autoload.php';
 *
 * One dependency, psr/event-dispatcher 1.0.0, is also in Joomla's own vendor
 * folder. It is a single interface at the same version in both, so whichever
 * autoloader gets to it first, the same interface is loaded once.
 */

\defined('_JEXEC') or die;

if (!class_exists(\League\CommonMark\MarkdownConverter::class, false)) {
    require_once __DIR__ . '/vendor/autoload.php';
}
