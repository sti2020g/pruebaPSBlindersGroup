<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'productbadges/classes/ProductBadge.php';

class AdminProductBadgesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap   = true;
        $this->table       = 'product_badge';
        $this->className   = 'ProductBadge';
        $this->lang        = true;
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->allow_export = false;

        parent::__construct();

        $this->fields_list = [
            'id_product_badge' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'label' => [
                'title'  => $this->l('Label'),
                'filter_key' => 'bl!label',
            ],
            'bg_color' => [
                'title'    => $this->l('Background'),
                'callback' => 'renderColorSwatch',
            ],
            'text_color' => [
                'title'    => $this->l('Text color'),
                'callback' => 'renderColorSwatch',
            ],
            'sort_order' => [
                'title' => $this->l('Order'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'position' => [
                'title' => $this->l('Position'),
            ],
            'active' => [
                'title'   => $this->l('Active'),
                'active'  => 'status',
                'type'    => 'bool',
                'align'   => 'center',
                'orderby' => false,
            ],
        ];
    }

    /**
     * Render a color swatch for HelperList callbacks.
     */
    public function renderColorSwatch(string $color): string
    {
        $safe = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

        return '<span style="display:inline-block;width:20px;height:20px;background:'
            . $safe . ';border:1px solid #ccc;vertical-align:middle;"></span> '
            . $safe;
    }

    // ─── Form ────────────────────────────────────────────────────────────────

    public function renderForm(): string
    {
        $idLang    = (int) $this->context->language->id;
        $languages = Language::getLanguages(false);
        $idShop    = (int) $this->context->shop->id;
        if ($idShop <= 0) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        $assigned = [];
        $badge    = [
            'id_product_badge' => 0,
            'label'            => [],
            'bg_color'         => '#ff0000',
            'text_color'       => '#ffffff',
            'position'         => 'top-left',
            'sort_order'       => 0,
            'active'           => 1,
        ];

        if ($this->object && $this->object->id) {
            /** @var ProductBadge $obj */
            $obj              = $this->object;
            $assigned         = $obj->getAssignedProducts();
            $badge['id_product_badge'] = (int) $obj->id;
            $badge['bg_color']   = $obj->bg_color;
            $badge['text_color'] = $obj->text_color;
            $badge['position']   = $obj->position;
            $badge['sort_order'] = (int) $obj->sort_order;
            $badge['active']     = (int) $obj->active;

            foreach ($languages as $lang) {
                $lid               = (int) $lang['id_lang'];
                $badge['label'][$lid] = $obj->label[$lid] ?? '';
            }
        } else {
            foreach ($languages as $lang) {
                $badge['label'][(int) $lang['id_lang']] = '';
            }
        }

        $products = $this->getAllProducts($idLang, $idShop);

        $this->context->smarty->assign([
            'badge'        => $badge,
            'languages'    => $languages,
            'default_lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
            'products'     => $products,
            'assigned_ids' => $assigned,
            'form_action'  => $this->context->link->getAdminLink('AdminProductBadges'),
            'cancel_url'   => $this->context->link->getAdminLink('AdminProductBadges'),
            'token_name'   => 'token',
            'token_value'  => Tools::getAdminTokenLite('AdminProductBadges'),
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'productbadges/views/templates/admin/badge_form.tpl'
        );
    }

    /**
     * Fetch all active products (id + name) for the given language.
     *
     * @return array
     */
    private function getAllProducts(int $idLang, int $idShop): array
    {
        $sql = new DbQuery();
        $sql->select('p.id_product, pl.name');
        $sql->from('product', 'p');
        $sql->innerJoin('product_lang', 'pl',
            'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang
        );
        // active flag lives in product_shop in PS 1.7
        $sql->innerJoin('product_shop', 'ps',
            'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . ' AND ps.active = 1'
        );
        $sql->orderBy('pl.name ASC');

        return (array) Db::getInstance()->executeS($sql);
    }

    // ─── Save ────────────────────────────────────────────────────────────────

    public function postProcess()
    {
        if (Tools::isSubmit('submitAddproduct_badge') || Tools::isSubmit('submitAddproduct_badgeAndStay')) {
            $this->validateAndSave();

            return;
        }

        parent::postProcess();
    }

    private function validateAndSave(): void
    {
        // ── Validate colors ──────────────────────────────────────────────────
        $bgColor   = Tools::getValue('bg_color');
        $textColor = Tools::getValue('text_color');

        if (!ProductBadge::validateColor($bgColor)) {
            $this->errors[] = $this->l('Invalid background color. Use #RRGGBB format.');
        }

        if (!ProductBadge::validateColor($textColor)) {
            $this->errors[] = $this->l('Invalid text color. Use #RRGGBB format.');
        }

        // ── Validate position ────────────────────────────────────────────────
        $position = Tools::getValue('position');
        if (!ProductBadge::validatePosition($position)) {
            $this->errors[] = $this->l('Invalid position value.');
        }

        // ── Validate labels ──────────────────────────────────────────────────
        $languages  = Language::getLanguages(false);
        $defaultId  = (int) Configuration::get('PS_LANG_DEFAULT');
        $hasDefault = false;

        foreach ($languages as $lang) {
            $langId = (int) $lang['id_lang'];
            $label  = Tools::getValue('label_' . $langId);

            if ($langId === $defaultId && empty($label)) {
                $this->errors[] = sprintf(
                    $this->l('Label is required for language: %s'),
                    htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8')
                );
            }

            if (!empty($label) && !Validate::isGenericName($label)) {
                $this->errors[] = sprintf(
                    $this->l('Invalid label for language: %s'),
                    htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8')
                );
            }

            if ($langId === $defaultId && !empty($label)) {
                $hasDefault = true;
            }
        }

        if (!empty($this->errors)) {
            return;
        }

        // ── Save object ──────────────────────────────────────────────────────
        $idBadge = (int) Tools::getValue('id_product_badge');
        $badge   = $idBadge ? new ProductBadge($idBadge) : new ProductBadge();

        $badge->bg_color   = $bgColor;
        $badge->text_color = $textColor;
        $badge->position   = $position;
        $badge->sort_order = max(0, (int) Tools::getValue('sort_order'));
        $badge->active     = (int) Tools::getValue('active');

        foreach ($languages as $lang) {
            $langId                 = (int) $lang['id_lang'];
            $badge->label[$langId] = Tools::getValue('label_' . $langId);
        }

        if ($badge->save()) {
            // ── Save product assignments ─────────────────────────────────────
            $rawIds     = Tools::getValue('product_ids', []);
            $productIds = $this->parseProductIds($rawIds);
            $badge->saveProductAssignments($productIds);

            $this->confirmations[] = $this->l('Badge saved.');

            if (Tools::isSubmit('submitAddproduct_badge')) {
                Tools::redirectAdmin(
                    $this->context->link->getAdminLink('AdminProductBadges')
                );
            }
        } else {
            $this->errors[] = $this->l('Error saving badge.');
        }
    }

    /**
     * Parse product IDs from multiselect POST array.
     * Accepts array (from <select multiple>) or empty string (nothing selected).
     * Returns only positive integers.
     *
     * @param mixed $raw  array from $_POST['product_ids'] or empty string
     * @return array<int>
     */
    private function parseProductIds($raw): array
    {
        if (empty($raw) || !is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }
}
