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
 * The half of `ImporterAwareInterface` every implementer would write the same.
 */
trait ImporterAwareTrait
{
    /** @var (\Closure(): Importer)|null */
    private ?\Closure $importerFactory = null;

    /**
     * @param   \Closure(): Importer  $factory
     */
    public function setImporterFactory(\Closure $factory): void
    {
        $this->importerFactory = $factory;
    }

    /**
     * @throws \UnexpectedValueException  When nothing handed it over: the object was made outside the component's factory.
     */
    protected function getImporter(): Importer
    {
        if ($this->importerFactory === null) {
            throw new \UnexpectedValueException(static::class . ' was not given the importer. Ask the component\'s MVC factory for it.');
        }

        return ($this->importerFactory)();
    }
}
