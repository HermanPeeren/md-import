<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Writes categories, articles and menu items through Joomla's own models.
 *
 * Through the models and not straight into the tables, because the models do
 * what a row alone does not: the asset for permissions, the workflow
 * association an article needs to show in its list, version history, the
 * nested-set bookkeeping of categories and menus, and the events other
 * extensions listen for.
 *
 * Each save gets a fresh model. An AdminModel remembers the id of what it last
 * saved, and a second "new" item saved through the same instance overwrites
 * the first.
 */
final class ContentWriter
{
    public function __construct(
        private readonly CMSApplicationInterface $app,
        private readonly DatabaseInterface $db
    ) {
    }

    /**
     * Create or update a com_content category, and return its id.
     *
     * @param   array<string, mixed>  $data
     */
    public function saveCategory(int $id, array $data): int
    {
        $model = $this->model('com_categories', 'Category');

        $data = array_merge(
            [
                'id'        => $id,
                'extension' => 'com_content',
                'published' => 1,
                'language'  => '*',
                'access'    => 1,
                'params'    => [],
                'metadata'  => [],
            ],
            $data
        );

        if (!$model->save($data)) {
            throw new \RuntimeException('Category "' . ($data['title'] ?? '') . '": ' . $model->getError());
        }

        return (int) $model->getState('category.id');
    }

    /**
     * Create or update an article, and return its id.
     *
     * @param   array<string, mixed>  $data
     */
    public function saveArticle(int $id, array $data): int
    {
        $model = $this->model('com_content', 'Article');

        $data = array_merge(
            [
                'id'         => $id,
                'state'      => 1,
                'language'   => '*',
                'access'     => 1,
                'featured'   => 0,
                'attribs'    => [],
                'metadata'   => [],
                'images'     => [],
                'urls'       => [],
                'metakey'    => '',
                'metadesc'   => '',
            ],
            $data
        );

        if (!$model->save($data)) {
            throw new \RuntimeException('Article "' . ($data['title'] ?? '') . '": ' . $model->getError());
        }

        return (int) $model->getState('article.id');
    }

    /**
     * Create or update a menu item, and return its id.
     *
     * An alias another item under the same parent already has is made unique
     * by numbering it, which is what Joomla's own Save as Copy does.
     *
     * @param   array<string, mixed>  $data
     */
    public function saveMenuItem(int $id, array $data): int
    {
        $data = array_merge(
            [
                'id'                => $id,
                'published'         => 1,
                'language'          => '*',
                'access'            => 1,
                'browserNav'        => 0,
                'template_style_id' => 0,
                'home'              => 0,
                'params'            => [],
                'menuordering'      => 0,
                'note'              => '',
            ],
            $data
        );

        $alias = (string) $data['alias'];
        $error = '';

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $model = $this->model('com_menus', 'Item');

            if ($model->save($data)) {
                return (int) $model->getState('item.id');
            }

            $error = (string) $model->getError();

            // The table refuses a duplicate alias with a message that names it;
            // anything else is a real failure.
            if (!str_contains(strtolower($error), 'alias')) {
                break;
            }

            $data['alias'] = $alias . '-' . ($attempt + 1);
        }

        throw new \RuntimeException('Menu item "' . ($data['title'] ?? '') . '": ' . $error);
    }

    /**
     * The menu an item is in, or null when there is no such item.
     */
    public function menuTypeOf(int $menuItemId): ?string
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('menutype'))
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->bind(':id', $menuItemId, ParameterType::INTEGER);

        $menutype = $this->db->setQuery($query)->loadResult();

        return $menutype ? (string) $menutype : null;
    }

    /**
     * The id of com_content in the extensions table, which a component menu
     * item has to carry.
     */
    public function contentComponentId(): int
    {
        return (int) ComponentHelper::getComponent('com_content')->id;
    }

    /**
     * An existing category under a parent with this alias, so that a first
     * import adopts a "help" category somebody already made instead of
     * failing on the duplicate alias.
     */
    public function findCategory(int $parentId, string $alias): int
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('parent_id') . ' = :parent')
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->where($this->db->quoteName('published') . ' >= 0')
            ->bind(':parent', $parentId, ParameterType::INTEGER)
            ->bind(':alias', $alias);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * An existing article in a category with this alias. See findCategory().
     */
    public function findArticle(int $categoryId, string $alias): int
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('catid') . ' = :catid')
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->where($this->db->quoteName('state') . ' >= 0')
            ->bind(':catid', $categoryId, ParameterType::INTEGER)
            ->bind(':alias', $alias);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    private function model(string $component, string $name): AdminModel
    {
        $extension = $this->app->bootComponent($component);

        if (!$extension instanceof MVCFactoryServiceInterface) {
            throw new \RuntimeException($component . ' has no MVC factory.');
        }

        $model = $extension->getMVCFactory()->createModel($name, 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof AdminModel) {
            throw new \RuntimeException('Joomla has no ' . $name . ' model in ' . $component . '.');
        }

        return $model;
    }
}
