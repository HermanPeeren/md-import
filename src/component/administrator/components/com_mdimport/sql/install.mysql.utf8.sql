--
-- What each imported page, section and image became, so that importing again
-- updates what is there instead of making a second copy of everything.
--
CREATE TABLE IF NOT EXISTS `#__mdimport_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_key` varchar(255) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `slug` varchar(255) NOT NULL DEFAULT '',
  `article_id` int unsigned NOT NULL DEFAULT 0,
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `menu_id` int unsigned NOT NULL DEFAULT 0,
  `hash` varchar(64) NOT NULL DEFAULT '',
  `imported` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_key` (`item_key`(191)),
  KEY `idx_kind_slug` (`kind`, `slug`(150))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
