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
 * Something that is handed the component's options, as the importer reads them rather than making it.
 *
 * Handed over by the component's MVC factory; see `Service\MVCFactory`.
 */
interface ImportSettingsAwareInterface
{
    public function setImportSettings(ImportSettings $importSettings): void;
}
