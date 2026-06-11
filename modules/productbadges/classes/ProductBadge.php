<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductBadge extends ObjectModel
{
    /** @var string */
    public $label;

    /** @var string */
    public $bg_color;

    /** @var string */
    public $text_color;

    /** @var string */
    public $position;

    /** @var int */
    public $sort_order;

    /** @var bool */
    public $active;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = [
        'table'     => 'product_badge',
        'primary'   => 'id_product_badge',
        'multilang' => true,
        'fields'    => [
            'bg_color'   => ['type' => self::TYPE_STRING, 'validate' => 'isColor', 'size' => 7, 'required' => true],
            'text_color' => ['type' => self::TYPE_STRING, 'validate' => 'isColor', 'size' => 7, 'required' => true],
            'position'   => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16, 'required' => true],
            'sort_order' => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'active'     => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add'   => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd'   => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            // lang fields
            'label'      => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 64, 'required' => true],
        ],
    ];

    /**
     * Get active badges for a product, respecting max limit from config.
     *
     * @param int $idProduct
     * @param int $idLang
     * @param int $maxBadges 0 = no limit
     * @return array
     */
    public static function getByProduct(int $idProduct, int $idLang, int $maxBadges = 0): array
    {
        $idProduct   = (int) $idProduct;
        $idLang      = (int) $idLang;
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop      = (int) Context::getContext()->shop->id;

        $limit = $maxBadges > 0 ? 'LIMIT ' . (int) $maxBadges : '';

        // id_shop = 0 → global (all shops); id_shop = N → specific shop only
        $query = '
            SELECT b.`id_product_badge`, b.`bg_color`, b.`text_color`, b.`position`, b.`sort_order`,
                   COALESCE(bl.`label`, bl_def.`label`, \'\') AS `label`
            FROM `' . _DB_PREFIX_ . 'product_badge` b
            INNER JOIN `' . _DB_PREFIX_ . 'product_badge_product` bp
                ON bp.`id_product_badge` = b.`id_product_badge`
                AND bp.`id_product` = ' . $idProduct . '
                AND (bp.`id_shop` = 0 OR bp.`id_shop` = ' . $idShop . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'product_badge_lang` bl
                ON bl.`id_product_badge` = b.`id_product_badge`
                AND bl.`id_lang` = ' . $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_badge_lang` bl_def
                ON bl_def.`id_product_badge` = b.`id_product_badge`
                AND bl_def.`id_lang` = ' . $defaultLang . '
            WHERE b.`active` = 1
            ORDER BY b.`sort_order` ASC, b.`id_product_badge` ASC
            ' . $limit;

        return (array) Db::getInstance()->executeS($query);
    }

    /**
     * Get all active badge assignments grouped by product ID.
     * Returns array keyed by id_product, each value is array of badge data.
     *
     * @param int $idLang
     * @return array  [ id_product => [ [label,bg_color,text_color,position,sort_order], ... ] ]
     */
    public static function getAllAssignments(int $idLang): array
    {
        $idLang      = (int) $idLang;
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop      = (int) Context::getContext()->shop->id;

        // id_shop = 0 → global (all shops); id_shop = N → specific shop only
        $query = '
            SELECT bp.`id_product`,
                   b.`bg_color`, b.`text_color`, b.`position`, b.`sort_order`,
                   COALESCE(bl.`label`, bl_def.`label`, \'\') AS `label`
            FROM `' . _DB_PREFIX_ . 'product_badge` b
            INNER JOIN `' . _DB_PREFIX_ . 'product_badge_product` bp
                ON bp.`id_product_badge` = b.`id_product_badge`
                AND (bp.`id_shop` = 0 OR bp.`id_shop` = ' . $idShop . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'product_badge_lang` bl
                ON bl.`id_product_badge` = b.`id_product_badge`
                AND bl.`id_lang` = ' . $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_badge_lang` bl_def
                ON bl_def.`id_product_badge` = b.`id_product_badge`
                AND bl_def.`id_lang` = ' . $defaultLang . '
            WHERE b.`active` = 1
            ORDER BY b.`sort_order` ASC, b.`id_product_badge` ASC';

        $rows = (array) Db::getInstance()->executeS($query);

        $grouped = [];
        foreach ($rows as $row) {
            $idProduct = (int) $row['id_product'];
            $grouped[$idProduct][] = [
                'label'    => $row['label'],
                'bg'       => $row['bg_color'],
                'text'     => $row['text_color'],
                'position' => $row['position'],
                'order'    => (int) $row['sort_order'],
            ];
        }

        return $grouped;
    }

    /**
     * Get product IDs assigned to this badge.
     *
     * @return array
     */
    public function getAssignedProducts(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_badge_product`
             WHERE id_product_badge = ' . (int) $this->id
        );

        if (!$rows) {
            return [];
        }

        return array_column($rows, 'id_product');
    }

    /**
     * Save many-to-many product assignments.
     * Validates each product ID before inserting.
     *
     * @param array $productIds
     * @return bool
     */
    public function saveProductAssignments(array $productIds): bool
    {
        $idBadge = (int) $this->id;

        // In multistore: assignments are shop-specific.
        // Without multistore (or when saving as global): id_shop = 0 (all shops).
        $idShop = Shop::isFeatureActive() ? (int) Context::getContext()->shop->id : 0;

        // Delete existing assignments for this badge + shop scope only
        if ($idShop === 0) {
            Db::getInstance()->delete('product_badge_product', 'id_product_badge = ' . $idBadge . ' AND id_shop = 0');
        } else {
            Db::getInstance()->delete(
                'product_badge_product',
                'id_product_badge = ' . $idBadge . ' AND id_shop = ' . $idShop
            );
        }

        foreach ($productIds as $idProduct) {
            $idProduct = (int) $idProduct;
            if ($idProduct <= 0) {
                continue;
            }

            $exists = (int) Db::getInstance()->getValue(
                'SELECT id_product FROM `' . _DB_PREFIX_ . 'product` WHERE id_product = ' . $idProduct
            );

            if (!$exists) {
                continue;
            }

            Db::getInstance()->insert('product_badge_product', [
                'id_product_badge' => $idBadge,
                'id_product'       => $idProduct,
                'id_shop'          => $idShop,
            ]);
        }

        return true;
    }

    /**
     * Validate hex color: must be #RRGGBB format.
     */
    public static function validateColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    /**
     * Validate position value.
     */
    public static function validatePosition(string $position): bool
    {
        return in_array($position, ['top-left', 'top-right'], true);
    }
}
