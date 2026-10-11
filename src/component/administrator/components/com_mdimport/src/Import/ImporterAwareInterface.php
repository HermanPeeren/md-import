<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

/**
 * Something that is handed a way to get the importer rather than making one.
 *
 * A factory and not the importer itself, because building the importer loads
 * CommonMark: the import screen should still open, and say what is wrong, on a
 * site where the library is missing.
 *
 * Handed over by the component's MVC factory; see `Service\MVCFactory`.
 */
interface ImporterAwareInterface
{
    /**
     * @param   \Closure(): Importer  $factory
     */
    public function setImporterFactory(\Closure $factory): void;
}
