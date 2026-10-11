<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactory as JoomlaMVCFactory;
use Joomla\Input\Input;
use Psr\Container\ContainerInterface;
use Yepr\Component\Mdimport\Administrator\Import\Importer;
use Yepr\Component\Mdimport\Administrator\Import\ImporterAwareInterface;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettingsAwareInterface;
use Yepr\Component\Mdimport\Administrator\Import\ItemMap;
use Yepr\Component\Mdimport\Administrator\Import\ItemMapAwareInterface;

/**
 * Joomla's MVC factory, handing this component's own services to what it makes.
 *
 * Joomla's factory builds every controller and model itself, with the
 * constructors Joomla decides, and then hands over the services it knows about
 * through `*AwareInterface` setters - the database, the form factory, the
 * dispatcher. This does the same for the services this component registers in
 * `Service\Provider\Import`: the settings, the item map and the importer.
 * Nothing in the MVC classes constructs them.
 *
 * The services are taken from the container when an object asks for one, not
 * when the factory is made, so a request that never imports never builds the
 * importer - or loads CommonMark.
 */
class MVCFactory extends JoomlaMVCFactory
{
    /**
     * @param  string              $namespace  The component's namespace.
     * @param  ContainerInterface  $services   The component's container, which holds the services.
     */
    public function __construct(string $namespace, private readonly ContainerInterface $services)
    {
        parent::__construct($namespace);
    }

    /**
     * @param   array<string, mixed>  $config
     */
    public function createController($name, $prefix, array $config, CMSApplicationInterface $app, Input $input)
    {
        return $this->handOver(parent::createController($name, $prefix, $config, $app, $input));
    }

    /**
     * @param   array<string, mixed>  $config
     */
    public function createModel($name, $prefix = '', array $config = [])
    {
        return $this->handOver(parent::createModel($name, $prefix, $config));
    }

    /**
     * Give an object the services it says it needs.
     *
     * @template T
     *
     * @param   T  $object  What the parent factory made, or null.
     *
     * @return  T
     */
    private function handOver($object)
    {
        if ($object instanceof ImportSettingsAwareInterface) {
            $object->setImportSettings($this->services->get(ImportSettings::class));
        }

        if ($object instanceof ItemMapAwareInterface) {
            $object->setItemMap($this->services->get(ItemMap::class));
        }

        if ($object instanceof ImporterAwareInterface) {
            // Handed over as a way to get one rather than the importer itself:
            // building it loads CommonMark, which a screen that only shows the
            // options has no reason to do - and which, if the library is
            // missing, would stop that screen from saying so.
            $object->setImporterFactory(fn (): Importer => $this->services->get(Importer::class));
        }

        return $object;
    }
}
