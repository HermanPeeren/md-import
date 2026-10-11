<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * What each page and section became on this site: `#__mdimport_items`.
 *
 * It is what makes importing twice safe. A page that was imported before is
 * updated in place - same article, same menu item - instead of a second copy
 * being made. When an article or menu item it remembers has since been
 * deleted, the importer notices and makes a new one.
 */
final class ItemMap
{
    private const TABLE = '#__mdimport_items';

    /** @var array<string, object>|null */
    private ?array $rows = null;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function get(string $key): ?object
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Every imported page by slug: its article and category.
     *
     * @return array<string, object>
     */
    public function pagesBySlug(): array
    {
        $pages = [];

        foreach ($this->all() as $row) {
            if ($row->kind === 'page' && (int) $row->article_id > 0) {
                $pages[$row->slug] = $row;
            }
        }

        return $pages;
    }

    /**
     * Record what a key became. Fields not given keep their value.
     *
     * @param   array<string, int|string>  $fields  article_id, category_id, menu_id, hash
     */
    public function save(string $key, string $kind, string $slug, array $fields): void
    {
        $existing = $this->get($key);
        $now      = gmdate('Y-m-d H:i:s');

        $row = (object) array_merge(
            [
                'item_key'    => $key,
                'kind'        => $kind,
                'slug'        => $slug,
                'article_id'  => (int) ($existing->article_id ?? 0),
                'category_id' => (int) ($existing->category_id ?? 0),
                'menu_id'     => (int) ($existing->menu_id ?? 0),
                'hash'        => (string) ($existing->hash ?? ''),
            ],
            $fields,
            ['imported' => $now]
        );

        if ($existing !== null) {
            $row->id = (int) $existing->id;
            $this->db->updateObject(self::TABLE, $row, 'id');
        } else {
            $this->db->insertObject(self::TABLE, $row, 'id');
        }

        $this->rows[$key] = $row;
    }

    /**
     * How many of each kind have been imported.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['page' => 0, 'section' => 0];

        foreach ($this->all() as $row) {
            $counts[$row->kind] = ($counts[$row->kind] ?? 0) + 1;
        }

        return $counts;
    }

    public function lastImport(): ?string
    {
        $query = $this->db->createQuery()
            ->select('MAX(' . $this->db->quoteName('imported') . ')')
            ->from($this->db->quoteName(self::TABLE));

        $value = $this->db->setQuery($query)->loadResult();

        return $value ? (string) $value : null;
    }

    /**
     * Whether a row still exists in a core table - an article or a menu item
     * the import made may since have been deleted by hand.
     */
    public function exists(string $table, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    /**
     * @return array<string, object>
     */
    private function all(): array
    {
        if ($this->rows === null) {
            $query = $this->db->createQuery()
                ->select('*')
                ->from($this->db->quoteName(self::TABLE));

            $this->rows = [];

            foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
                $this->rows[$row->item_key] = $row;
            }
        }

        return $this->rows;
    }
}
