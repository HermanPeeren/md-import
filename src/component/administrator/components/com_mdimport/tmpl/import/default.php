<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Yepr\Component\Mdimport\Administrator\View\Import\HtmlView $this */

$settings = $this->settings;
$wa       = $this->getDocument()->getWebAssetManager();
$wa->useScript('com_mdimport.import');

Text::script('COM_MDIMPORT_IMPORT_RUNNING');
Text::script('COM_MDIMPORT_IMPORT_FINISHED');
Text::script('COM_MDIMPORT_IMPORT_FAILED');
Text::script('COM_MDIMPORT_STEP_DOWNLOAD');
Text::script('COM_MDIMPORT_STEP_IMAGES');
Text::script('COM_MDIMPORT_STEP_CATEGORIES');
Text::script('COM_MDIMPORT_STEP_ARTICLES');
Text::script('COM_MDIMPORT_STEP_CONTENT');
Text::script('COM_MDIMPORT_STEP_MENU');
Text::script('COM_MDIMPORT_STEP_CLEANUP');

$endpoint = Route::_('index.php?option=com_mdimport&task=import.step&format=json', false);
$source   = 'https://github.com/' . $settings->owner . '/' . $settings->repository . '/tree/' . $settings->branch;
$noValue  = '<em>' . Text::_('COM_MDIMPORT_NOT_SET') . '</em>';
?>
<div id="mdimport" class="row"
     data-endpoint="<?php echo $this->escape($endpoint); ?>"
     data-token="<?php echo Session::getFormToken(); ?>">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::_('COM_MDIMPORT_WHAT_HEADING'); ?></h2>
                <p><?php echo Text::_('COM_MDIMPORT_WHAT_TEXT'); ?></p>

                <table class="table table-sm mdimport-summary">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_SUMMARY_SOURCE'); ?></th>
                            <td><a href="<?php echo $this->escape($source); ?>" target="_blank" rel="noopener"><?php echo $this->escape($settings->owner . '/' . $settings->repository); ?></a>
                                (<?php echo $this->escape($settings->branch); ?>)</td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_FIELD_PARENT_MENU_ITEM_LABEL'); ?></th>
                            <td class="mdimport-parent-menu-item"><?php echo $this->parentMenuItemTitle !== '' ? $this->escape($this->parentMenuItemTitle) : $noValue; ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_FIELD_SECTION_MENU_TYPE_LABEL'); ?></th>
                            <td><?php echo Text::_('COM_MDIMPORT_OPTION_SECTION_' . strtoupper($settings->sectionMenuType)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_SUMMARY_IMAGES'); ?></th>
                            <td><code class="mdimport-image-base"><?php echo $this->escape($settings->imageBase()); ?>/</code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_FIELD_PARENT_CATEGORY_LABEL'); ?></th>
                            <td><?php echo $this->escape($this->parentCategoryTitle); ?>
                                <?php if ($settings->sectionCategories) : ?>
                                    <br><small class="text-body-secondary"><?php echo Text::_('COM_MDIMPORT_SUMMARY_SECTION_CATEGORIES'); ?></small>
                                <?php endif; ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_FIELD_ON_EXISTING_LABEL'); ?></th>
                            <td><?php echo Text::_($settings->updateExisting ? 'COM_MDIMPORT_OPTION_UPDATE' : 'COM_MDIMPORT_OPTION_SKIP'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_MDIMPORT_SUMMARY_CONVERSION'); ?></th>
                            <td>league/commonmark &middot; <?php echo Text::_($settings->conversion->flavor === 'gfm' ? 'COM_MDIMPORT_OPTION_FLAVOR_GFM' : 'COM_MDIMPORT_OPTION_FLAVOR_COMMONMARK'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php foreach ($this->problems as $problem) : ?>
                    <div class="alert alert-warning mdimport-problem"><?php echo $this->escape($problem); ?></div>
                <?php endforeach; ?>

                <?php if ($this->canImport) : ?>
                    <button type="button" class="btn btn-primary btn-lg" id="mdimport-start"<?php echo $this->problems !== [] ? ' disabled' : ''; ?>>
                        <span class="icon-file-import" aria-hidden="true"></span>
                        <?php echo Text::_('COM_MDIMPORT_START'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3 d-none" id="mdimport-progress-card">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::_('COM_MDIMPORT_PROGRESS_HEADING'); ?></h2>
                <div class="progress mb-3" role="progressbar" aria-label="<?php echo Text::_('COM_MDIMPORT_PROGRESS_HEADING'); ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" id="mdimport-progress" style="width: 0%">0%</div>
                </div>
                <ol class="list-unstyled mb-0" id="mdimport-log" aria-live="polite"></ol>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::_('COM_MDIMPORT_STATUS_HEADING'); ?></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-6"><?php echo Text::_('COM_MDIMPORT_STATUS_PAGES'); ?></dt>
                    <dd class="col-sm-6" id="mdimport-count-pages"><?php echo (int) $this->counts['page']; ?></dd>
                    <dt class="col-sm-6"><?php echo Text::_('COM_MDIMPORT_STATUS_SECTIONS'); ?></dt>
                    <dd class="col-sm-6"><?php echo (int) $this->counts['section']; ?></dd>
                    <dt class="col-sm-6"><?php echo Text::_('COM_MDIMPORT_STATUS_LAST'); ?></dt>
                    <dd class="col-sm-6"><?php echo $this->lastImport ? HTMLHelper::_('date', $this->lastImport, Text::_('DATE_FORMAT_LC5')) : $noValue; ?></dd>
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::_('COM_MDIMPORT_LICENCE_HEADING'); ?></h2>
                <p class="mb-0"><?php echo Text::_('COM_MDIMPORT_LICENCE_TEXT'); ?></p>
            </div>
        </div>
    </div>
</div>
