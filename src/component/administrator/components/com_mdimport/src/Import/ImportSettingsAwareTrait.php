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
 * The half of `ImportSettingsAwareInterface` every implementer would write the same.
 */
trait ImportSettingsAwareTrait
{
    private ?ImportSettings $importSettings = null;

    public function setImportSettings(ImportSettings $importSettings): void
    {
        $this->importSettings = $importSettings;
    }

    /**
     * @throws \UnexpectedValueException  When nothing handed it over: the object was made outside the component's factory.
     */
    protected function getImportSettings(): ImportSettings
    {
        return $this->importSettings ?? throw new \UnexpectedValueException(
            static::class . ' was not given the ImportSettings. Ask the component\'s MVC factory for it.'
        );
    }
}
