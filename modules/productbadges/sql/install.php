<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product_badge` (
    `id_product_badge` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `bg_color` varchar(7) NOT NULL DEFAULT \'#000000\',
    `text_color` varchar(7) NOT NULL DEFAULT \'#ffffff\',
    `position` enum(\'top-left\',\'top-right\') NOT NULL DEFAULT \'top-left\',
    `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
    `all_products` tinyint(1) unsigned NOT NULL DEFAULT 0,
    `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_product_badge`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product_badge_lang` (
    `id_product_badge` int(10) unsigned NOT NULL,
    `id_lang` int(10) unsigned NOT NULL,
    `label` varchar(64) NOT NULL DEFAULT \'\',
    PRIMARY KEY (`id_product_badge`, `id_lang`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product_badge_product` (
    `id_product_badge` int(10) unsigned NOT NULL,
    `id_product` int(10) unsigned NOT NULL,
    PRIMARY KEY (`id_product_badge`, `id_product`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
