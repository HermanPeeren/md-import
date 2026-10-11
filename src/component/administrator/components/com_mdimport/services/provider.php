<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Mdimport\Administrator\Extension\MdimportComponent;
use Yepr\Component\Mdimport\Administrator\Service\Provider\Import;
use Yepr\Component\Mdimport\Administrator\Service\Provider\MVCFactory;

/**
 * The MD Import service provider: the composition root.
 *
 * Every service this component has is registered here or by a provider named
 * here, and nothing else in the component constructs one. Its own - the
 * settings, the item map, the writers, the converter and the importer - are
 * registered by `Import`, and this component's MVC factory hands them to
 * models and controllers the way Joomla's hands over the database
 * (`MVCFactory`, this component's, in place of Joomla's).
 */
return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new Import());
        $container->registerServiceProvider(new MVCFactory('\\Yepr\\Component\\Mdimport'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Yepr\\Component\\Mdimport'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new MdimportComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
