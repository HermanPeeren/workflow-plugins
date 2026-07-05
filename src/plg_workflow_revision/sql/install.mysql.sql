CREATE TABLE IF NOT EXISTS `#__revision_copy_original` (
    `original_id` int UNSIGNED NOT NULL DEFAULT 0,
    `copy_id` int UNSIGNED NOT NULL DEFAULT 0,
    `context` varchar(255) NOT NULL DEFAULT 'com_content.article',
    `modified_by` int UNSIGNED NOT NULL DEFAULT 0,
    `modified_time` datetime NOT NULL,
    PRIMARY KEY (`context`, `original_id`),
    UNIQUE KEY `copy` (`context`, `copy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;