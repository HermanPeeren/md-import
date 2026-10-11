<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;

/**
 * Install script for the MD Import package.
 *
 * The package carries the CommonMark library and the component, and Joomla
 * installs them in the order the manifest lists them. All this does is refuse
 * an environment neither can run in, before anything has been written.
 */
class Pkg_MdimportInstallerScript
{
    /**
     * The oldest Joomla this runs on. The component boots core's own article,
     * category and menu item models, which have the shape it relies on from 5.0.
     *
     * @var string
     */
    private $minimumJoomlaVersion = '5.0';

    /**
     * The oldest PHP this runs on: league/commonmark 2.x and readonly properties.
     *
     * @var string
     */
    private $minimumPHPVersion = '8.1';

    /**
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  False stops the installation.
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion), 'error');

            return false;
        }

        return true;
    }
}
