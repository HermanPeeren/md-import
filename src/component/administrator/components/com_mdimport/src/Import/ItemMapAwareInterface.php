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
 * Something that is handed the record of what each page and section became rather than making it.
 *
 * Handed over by the component's MVC factory; see `Service\MVCFactory`.
 */
interface ItemMapAwareInterface
{
    public function setItemMap(ItemMap $itemMap): void;
}
