<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\View\Import;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;
use Yepr\Component\Mdimport\Administrator\Model\ImportModel;

/**
 * Where the help pages come from, where they will go, and the button.
 */
final class HtmlView extends BaseHtmlView
{
    public ImportSettings $settings;

    /** @var string[] */
    public array $problems = [];

    /** @var array<string, int> */
    public array $counts = [];

    public ?string $lastImport = null;

    public string $parentMenuItemTitle = '';

    public string $parentCategoryTitle = '';

    public bool $canImport = false;

    public function display($tpl = null): void
    {
        /** @var ImportModel $model */
        $model = $this->getModel();

        $this->settings   = $model->settings();
        $this->counts     = $model->map()->counts();
        $this->lastImport = $model->map()->lastImport();
        $this->canImport  = Factory::getApplication()->getIdentity()->authorise('mdimport.import', 'com_mdimport');

        // Building the importer loads CommonMark, so without the library there
        // is no importer to ask - and that is the one problem worth reporting.
        if (!ImportModel::commonMarkAvailable()) {
            $this->problems[] = Text::_('COM_MDIMPORT_PROBLEM_NO_LIBRARY');
        } else {
            $this->problems = $model->importer()->problems();
        }

        $this->parentMenuItemTitle = $this->title('#__menu', $this->settings->parentMenuItem);
        $this->parentCategoryTitle = $this->settings->parentCategory > 0
            ? $this->title('#__categories', $this->settings->parentCategory)
            : Text::_('COM_MDIMPORT_OPTION_CREATE_HELP_CATEGORY');

        $this->toolbar();

        parent::display($tpl);
    }

    private function toolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_MDIMPORT'), 'file-import');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_mdimport')) {
            ToolbarHelper::preferences('com_mdimport');
        }
    }

    private function title(string $table, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName('title'))
            ->from($db->quoteName($table))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }
}
