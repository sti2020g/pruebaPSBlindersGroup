<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductBadge.php';

class ProductBadges extends Module
{
    public function __construct()
    {
        $this->name      = 'productbadges';
        $this->tab       = 'front_office_features';
        $this->version   = '1.0.0';
        $this->author    = 'Rubén Amaya Omenat';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Etiquetador de Productos');
        $this->description = $this->l('Gestionar las etiquetas de productos "NUEVO", "OFERTA", "EXCLUSIVO", "ÚLTIMAS UNIDADES", etc.');
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    // ─── Install / Uninstall ─────────────────────────────────────────────────

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->runSqlFile('install')) {
            return false;
        }

        if (!$this->registerHooks()) {
            return false;
        }

        if (!$this->installTab()) {
            return false;
        }

        $this->setDefaultConfig();

        return true;
    }

    public function uninstall(): bool
    {
        if (!parent::uninstall()) {
            return false;
        }

        $this->runSqlFile('uninstall');
        $this->uninstallTab();
        $this->deleteConfig();

        return true;
    }

    private function runSqlFile(string $file): bool
    {
        $path = __DIR__ . '/sql/' . $file . '.php';
        if (!file_exists($path)) {
            return false;
        }

        return (bool) require $path;
    }

    private function registerHooks(): bool
    {
        $hooks = [
            'displayProductListItem',        // category / search listing
            'displayProductAdditionalInfo',  // product page (PS 1.7 standard)
            'displayHeader',                 // load CSS/JS on front
            'actionAdminControllerSetMedia', // load CSS/JS in back
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    private function installTab(): bool
    {
        $tab             = new Tab();
        $tab->active     = 1;
        $tab->class_name = 'AdminProductBadges';
        $tab->module     = $this->name;
        $tab->id_parent  = (int) Tab::getIdFromClassName('AdminCatalog');

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Product Badges');
        }

        return (bool) $tab->add();
    }

    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminProductBadges');
        if (!$idTab) {
            return true;
        }

        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    private function setDefaultConfig(): void
    {
        Configuration::updateValue('PRODUCTBADGES_ENABLED', 1);
        Configuration::updateValue('PRODUCTBADGES_SHOW_LISTING', 1);
        Configuration::updateValue('PRODUCTBADGES_SHOW_PRODUCT', 1);
        Configuration::updateValue('PRODUCTBADGES_MAX_BADGES', 3);
        Configuration::updateValue('PRODUCTBADGES_HIDE_FLAGS', 1);
    }

    private function deleteConfig(): void
    {
        Configuration::deleteByName('PRODUCTBADGES_ENABLED');
        Configuration::deleteByName('PRODUCTBADGES_SHOW_LISTING');
        Configuration::deleteByName('PRODUCTBADGES_SHOW_PRODUCT');
        Configuration::deleteByName('PRODUCTBADGES_MAX_BADGES');
        Configuration::deleteByName('PRODUCTBADGES_HIDE_FLAGS');
    }

    // ─── Back-office config page ─────────────────────────────────────────────

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitProductBadgesConfig')) {
            $output .= $this->saveConfig();
        }

        $badgesUrl = $this->context->link->getAdminLink('AdminProductBadges');
        $output .= '<div class="alert alert-info" style="margin-bottom:16px;">'
            . '<a href="' . htmlspecialchars($badgesUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">'
            . '<i class="icon-tag"></i> ' . $this->l('Manage Badges') . '</a></div>';

        return $output . $this->renderConfigForm();
    }

    private function saveConfig(): string
    {
        $enabled          = (int) Tools::getValue('PRODUCTBADGES_ENABLED');
        $showListing      = (int) Tools::getValue('PRODUCTBADGES_SHOW_LISTING');
        $showProduct      = (int) Tools::getValue('PRODUCTBADGES_SHOW_PRODUCT');
        $maxBadges        = (int) Tools::getValue('PRODUCTBADGES_MAX_BADGES');
        $hideDefaultFlags = (int) Tools::getValue('PRODUCTBADGES_HIDE_FLAGS');

        if ($maxBadges < 0 || $maxBadges > 20) {
            return $this->displayError($this->l('Max badges must be between 0 and 20.'));
        }

        Configuration::updateValue('PRODUCTBADGES_ENABLED', $enabled ? 1 : 0);
        Configuration::updateValue('PRODUCTBADGES_SHOW_LISTING', $showListing ? 1 : 0);
        Configuration::updateValue('PRODUCTBADGES_SHOW_PRODUCT', $showProduct ? 1 : 0);
        Configuration::updateValue('PRODUCTBADGES_MAX_BADGES', $maxBadges);
        Configuration::updateValue('PRODUCTBADGES_HIDE_FLAGS', $hideDefaultFlags ? 1 : 0);

        return $this->displayConfirmation($this->l('Settings saved.'));
    }

    private function renderConfigForm(): string
    {
        $fields = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable module'),
                        'name'    => 'PRODUCTBADGES_ENABLED',
                        'values'  => $this->getSwitchValues('PRODUCTBADGES_ENABLED'),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show in product listings'),
                        'name'    => 'PRODUCTBADGES_SHOW_LISTING',
                        'values'  => $this->getSwitchValues('PRODUCTBADGES_SHOW_LISTING'),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show on product page'),
                        'name'    => 'PRODUCTBADGES_SHOW_PRODUCT',
                        'values'  => $this->getSwitchValues('PRODUCTBADGES_SHOW_PRODUCT'),
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Maximum badges per product'),
                        'name'     => 'PRODUCTBADGES_MAX_BADGES',
                        'class'    => 'fixed-width-sm',
                        'desc'     => $this->l('Set 0 for unlimited.'),
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Hide default theme badges (New, Sale, etc.)'),
                        'name'   => 'PRODUCTBADGES_HIDE_FLAGS',
                        'desc'   => $this->l('Hides .product-flags elements added by the theme.'),
                        'values' => $this->getSwitchValues('PRODUCTBADGES_HIDE_FLAGS'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper                           = new HelperForm();
        $helper->module                   = $this;
        $helper->name_controller          = $this->name;
        $helper->token                    = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex             = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language    = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->show_toolbar             = false;
        $helper->submit_action            = 'submitProductBadgesConfig';

        $helper->fields_value['PRODUCTBADGES_ENABLED']      = (int) Configuration::get('PRODUCTBADGES_ENABLED');
        $helper->fields_value['PRODUCTBADGES_SHOW_LISTING'] = (int) Configuration::get('PRODUCTBADGES_SHOW_LISTING');
        $helper->fields_value['PRODUCTBADGES_SHOW_PRODUCT'] = (int) Configuration::get('PRODUCTBADGES_SHOW_PRODUCT');
        $helper->fields_value['PRODUCTBADGES_MAX_BADGES']   = (int) Configuration::get('PRODUCTBADGES_MAX_BADGES');
        $helper->fields_value['PRODUCTBADGES_HIDE_FLAGS']   = (int) Configuration::get('PRODUCTBADGES_HIDE_FLAGS');

        return $helper->generateForm([$fields]);
    }

    private function getSwitchValues(string $fieldName = ''): array
    {
        return [
            ['id' => $fieldName . '_on', 'value' => 1, 'label' => $this->l('Yes')],
            ['id' => $fieldName . '_off', 'value' => 0, 'label' => $this->l('No')],
        ];
    }

    // ─── Front-office hooks ──────────────────────────────────────────────────

    public function hookDisplayHeader(): string
    {
        if (!(int) Configuration::get('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        $this->context->controller->addCSS($this->_path . 'views/css/productbadgesv4.css');
        $this->context->controller->addJS($this->_path . 'views/js/productbadgesv5.js');

        $idLang      = (int) $this->context->language->id;
        $assignments = ProductBadge::getAllAssignments($idLang);
        $maxBadges   = (int) Configuration::get('PRODUCTBADGES_MAX_BADGES');

        $output = '<div id="productbadges-data"'
            . ' data-badges="' . htmlspecialchars(json_encode($assignments), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-max="' . (int) $maxBadges . '"'
            . ' style="display:none"></div>';

        if ((int) Configuration::get('PRODUCTBADGES_HIDE_FLAGS')) {
            $output .= '<style>.product-flags{display:none!important}</style>';
        }

        return $output;
    }

    public function hookActionAdminControllerSetMedia(): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/productbadges-admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/productbadges-admin.js');
    }

    public function hookDisplayProductListItem(array $params): string
    {
        if (!(int) Configuration::get('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        if (!(int) Configuration::get('PRODUCTBADGES_SHOW_LISTING')) {
            return '';
        }

        $idProduct = (int) ($params['product']['id_product']
            ?? $params['product']['id']
            ?? 0);

        return $this->renderBadgesForProduct($idProduct);
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!(int) Configuration::get('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        if (!(int) Configuration::get('PRODUCTBADGES_SHOW_PRODUCT')) {
            return '';
        }

        $idProduct = isset($params['product']['id_product'])
            ? (int) $params['product']['id_product']
            : (int) Tools::getValue('id_product');

        return $this->renderBadgesForProduct($idProduct);
    }

    private function renderBadgesForProduct(int $idProduct): string
    {
        if ($idProduct <= 0) {
            return '';
        }

        $maxBadges = (int) Configuration::get('PRODUCTBADGES_MAX_BADGES');
        $idLang    = (int) $this->context->language->id;
        $badges    = ProductBadge::getByProduct($idProduct, $idLang, $maxBadges);

        if (empty($badges)) {
            return '';
        }

        foreach ($badges as &$badge) {
            $badge['label']      = htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8');
            $badge['bg_color']   = $this->sanitizeColor($badge['bg_color']);
            $badge['text_color'] = $this->sanitizeColor($badge['text_color']);
            $badge['position']   = ProductBadge::validatePosition($badge['position'])
                ? $badge['position']
                : 'top-left';
        }
        unset($badge);

        $this->context->smarty->assign('productbadges_badges', $badges);

        return $this->display(__FILE__, 'views/templates/hook/badges.tpl');
    }

    /**
     * Ensure color is a valid #RRGGBB before output.
     * Falls back to #000000 if invalid.
     */
    private function sanitizeColor(string $color): string
    {
        return ProductBadge::validateColor($color) ? $color : '#000000';
    }
}
