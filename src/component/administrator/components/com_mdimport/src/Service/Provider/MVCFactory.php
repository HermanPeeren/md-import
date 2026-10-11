<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Service\Provider;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Yepr\Component\Mdimport\Administrator\Service\MVCFactory as MdimportMVCFactory;

/**
 * Registers this component's MVC factory in place of Joomla's.
 *
 * The same wiring as `Joomla\CMS\Extension\Service\Provider\MVCFactory`, so the
 * models get everything they got before, with one difference: the factory is
 * this component's, and it is given the container its own services are in.
 * There is no API client branch, because this component has no API.
 */
final class MVCFactory implements ServiceProviderInterface
{
    /**
     * @param  string  $namespace  The component's namespace.
     */
    public function __construct(private readonly string $namespace)
    {
    }

    public function register(Container $container): void
    {
        $container->set(
            MVCFactoryInterface::class,
            function (Container $container) {
                $factory = new MdimportMVCFactory($this->namespace, $container);

                $factory->setFormFactory($container->get(FormFactoryInterface::class));
                $factory->setDispatcher($container->get(DispatcherInterface::class));
                $factory->setDatabase($container->get(DatabaseInterface::class));
                $factory->setSiteRouter($container->get(SiteRouter::class));
                $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
                $factory->setUserFactory($container->get(UserFactoryInterface::class));
                $factory->setMailerFactory($container->get(MailerFactoryInterface::class));

                return $factory;
            }
        );
    }
}
