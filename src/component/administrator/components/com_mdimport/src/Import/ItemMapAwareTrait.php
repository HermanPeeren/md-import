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
 * The half of `ItemMapAwareInterface` every implementer would write the same.
 */
trait ItemMapAwareTrait
{
    private ?ItemMap $itemMap = null;

    public function setItemMap(ItemMap $itemMap): void
    {
        $this->itemMap = $itemMap;
    }

    /**
     * @throws \UnexpectedValueException  When nothing handed it over: the object was made outside the component's factory.
     */
    protected function getItemMap(): ItemMap
    {
        return $this->itemMap ?? throw new \UnexpectedValueException(
            static::class . ' was not given the ItemMap. Ask the component\'s MVC factory for it.'
        );
    }
}
