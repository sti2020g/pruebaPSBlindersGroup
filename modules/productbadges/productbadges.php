<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductBadgeMultiStorev1.php';

class ProductBadges extends Module
{
    public function __construct()
    {
        $this->name      = 'productbadges';
        $this->tab       = 'front_office_features';
        $this->version   = '2.0.0';
        $this->author    = 'Rubén Amaya Omenat';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Etiquetador MultiStore');
        $this->description = $this->l('Gestiona etiquetas tipo NUEVO, PROMOCIÓN, ... en tu store o multistore.');
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

        // Register for every existing shop so multistore works even if shop 2
        // was created after the module was first installed.
        $shopIds = array_column(Shop::getShops(true), 'id_shop');

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook, $shopIds ?: null)) {
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
        // Save globally (id_shop = null) via direct DB write — guaranteed to create
        // a true global row regardless of the current shop context.
        $this->writeConfigRow('PRODUCTBADGES_ENABLED', 1, null);
        $this->writeConfigRow('PRODUCTBADGES_SHOW_LISTING', 1, null);
        $this->writeConfigRow('PRODUCTBADGES_SHOW_PRODUCT', 1, null);
        $this->writeConfigRow('PRODUCTBADGES_MAX_BADGES', 3, null);
        $this->writeConfigRow('PRODUCTBADGES_HIDE_FLAGS', 1, null);
    }

    private function deleteConfig(): void
    {
        Configuration::deleteByName('PRODUCTBADGES_ENABLED');
        Configuration::deleteByName('PRODUCTBADGES_SHOW_LISTING');
        Configuration::deleteByName('PRODUCTBADGES_SHOW_PRODUCT');
        Configuration::deleteByName('PRODUCTBADGES_MAX_BADGES');
        Configuration::deleteByName('PRODUCTBADGES_HIDE_FLAGS');
    }

    /**
     * Returns shop ID when multistore is active, null otherwise.
     * Used for admin config read/write (explicit per-shop override).
     */
    private function getShopId(): ?int
    {
        return Shop::isFeatureActive() ? (int) $this->context->shop->id : null;
    }

    /**
     * Read config for a given shop with 3-step cascade.
     *
     * NOTE: PS stores id_shop_group as integer 0 (not NULL) when in "all shops"
     * context. ALL conditions must accept BOTH `IS NULL` and `= 0` to match
     * rows written by PS's Configuration::updateValue().
     *
     * Cascade order:
     *   1. Shop-specific row  (id_shop = $idShop)
     *   2. Global row         (id_shop = 0 or NULL)
     *   3. Any existing row   — legacy fallback
     *
     * @param string   $key
     * @param int|null $idShop  null = skip step 1 (read global only)
     */
    /**
     * Read one scalar value from ps_configuration via executeS() — never getValue().
     * Db::getValue() is unreliable on this PS build (returns stale data even with
     * use_cache=false). executeS() always queries the DB fresh.
     */
    private function execQueryValue(string $sql): ?string
    {
        $rows = Db::getInstance()->executeS($sql, true, false);
        if (!is_array($rows) || empty($rows)) {
            return null;
        }
        $row = reset($rows);
        return $row ? (string) array_shift($row) : null;
    }

    private function getConfigValueForShop(string $key, ?int $idShop): int
    {
        $safeKey = pSQL($key);
        $pfx     = _DB_PREFIX_;

        // 1. Shop-specific (id_shop = X, group = 0 or NULL)
        if ($idShop !== null && $idShop > 0) {
            $val = $this->execQueryValue(
                'SELECT `value` FROM `' . $pfx . 'configuration`
                 WHERE `name` = \'' . $safeKey . '\'
                   AND `id_shop` = ' . (int) $idShop . '
                   AND (`id_shop_group` IS NULL OR `id_shop_group` = 0)
                 ORDER BY `id_configuration` ASC LIMIT 1'
            );
            if ($val !== null && $val !== '') {
                return (int) $val;
            }
        }

        // 2. Global (id_shop = 0 or NULL, group = 0 or NULL)
        $val = $this->execQueryValue(
            'SELECT `value` FROM `' . $pfx . 'configuration`
             WHERE `name` = \'' . $safeKey . '\'
               AND (`id_shop` IS NULL OR `id_shop` = 0)
               AND (`id_shop_group` IS NULL OR `id_shop_group` = 0)
             ORDER BY `id_configuration` ASC LIMIT 1'
        );
        if ($val !== null && $val !== '') {
            return (int) $val;
        }

        // 3. Any existing value — covers old installs with different conventions
        $val = $this->execQueryValue(
            'SELECT `value` FROM `' . $pfx . 'configuration`
             WHERE `name` = \'' . $safeKey . '\'
             ORDER BY `id_configuration` ASC LIMIT 1'
        );

        return $val !== null ? (int) $val : 0;
    }

    /**
     * Read config using the current request's shop context.
     * Used by front-office hooks.
     */
    private function getConfigValue(string $key): int
    {
        return $this->getConfigValueForShop($key, $this->getShopId());
    }

    // ─── Back-office config page ─────────────────────────────────────────────

    public function getContent(): string
    {
        $this->registerHooks();
        $this->ensureGlobalConfig();

        $shops        = Shop::isFeatureActive() ? Shop::getShops(true) : [];
        $validShopIds = array_map('intval', array_column($shops, 'id_shop'));

        $selectedShopId = (int) Tools::getValue('id_shop', 0);
        if (empty($validShopIds) || !in_array($selectedShopId, $validShopIds)) {
            $selectedShopId = !empty($validShopIds) ? $validShopIds[0] : 0;
        }
        $idShopForConfig = $selectedShopId > 0 ? $selectedShopId : null;

        $output = '';

        if (Tools::isSubmit('submitProductBadgesConfig')) {
            $output .= $this->saveConfig($idShopForConfig);
        }

        // 1. Light-green Manage Badges shortcut (outside blue panel)
        $output .= $this->renderManageBadgesPanel();

        // 2. Blue panel wrapping: selector + settings form + per-shop table
        $output .= $this->renderBlueConfigPanel($shops, $selectedShopId, $idShopForConfig);

        return $output;
    }

    /**
     * Blue outer panel containing: store selector, settings form, summary table.
     */
    private function renderBlueConfigPanel(array $shops, int $selectedShopId, ?int $idShopForConfig): string
    {
        $multistore = count($shops) > 1;
        $configUrl  = $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name;

        // ── Open blue panel ──────────────────────────────────────────────
        $html  = '<div style="border:2px solid #25b9d7;border-radius:4px;'
            . 'margin-bottom:16px;background:#fff;">';
        $html .= '<div style="background:#25b9d7;padding:10px 18px;'
            . 'border-radius:2px 2px 0 0;">'
            . '<i class="icon-home" style="color:#fff;"></i>&nbsp;'
            . '<strong style="color:#fff;font-size:14px;">'
            . $this->l('Select Store') . '</strong>'
            . '</div>';

        // ── Shop selector dropdown ────────────────────────────────────────
        if ($multistore) {
            $html .= '<div style="background:#eaf7fb;padding:12px 18px 10px;'
                . 'border-bottom:1px solid #c5e8f5;">';
            $html .= '<span style="color:#1a8fa8;font-weight:bold;margin-right:10px;">'
                . $this->l('Configure settings for:') . '</span>';
            $html .= '<select id="pb_config_shop_selector" '
                . 'style="display:inline-block;width:auto;max-width:280px;vertical-align:middle;" '
                . 'class="form-control">';

            foreach ($shops as $shop) {
                $sid  = (int) $shop['id_shop'];
                $sel  = ($sid === $selectedShopId) ? ' selected' : '';
                $name = htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8');
                $html .= '<option value="' . $sid . '"' . $sel . '>' . $name . '</option>';
            }

            $html .= '</select>';
            $html .= '<small style="display:block;color:#1a8fa8;margin-top:5px;">'
                . $this->l('Each store saves its own independent settings.') . '</small>';
            $html .= '</div>';

            $safeUrl = addslashes($configUrl);
            $html .= '<script>(function(){'
                . 'var s=document.getElementById("pb_config_shop_selector");'
                . 'if(!s){return;}'
                . 's.addEventListener("change",function(){'
                . 'window.location.href="' . $safeUrl . '&id_shop="+encodeURIComponent(this.value);'
                . '});'
                . '}());</script>';
        }

        // ── General Settings form ─────────────────────────────────────────
        $html .= '<div style="padding:4px 0 0;">'
            . $this->renderConfigForm($idShopForConfig)
            . '</div>';

        // ── Per-shop summary table (BOTTOM, multistore only) ──────────────
        if ($multistore) {
            $html .= $this->renderConfigTable($shops, $selectedShopId);
        }

        // ── Close blue panel ──────────────────────────────────────────────
        $html .= '</div>';

        return $html;
    }

    /**
     * Light-green shortcut panel to the badge manager.
     */
    private function renderManageBadgesPanel(): string
    {
        $badgesUrl = $this->context->link->getAdminLink('AdminProductBadges');

        $html  = '<div class="panel" '
            . 'style="background:#f0fff4;border:1px solid #a3d9b1;margin-bottom:16px;">';
        $html .= '<div style="padding:12px 18px;">';
        $html .= '<a href="' . htmlspecialchars($badgesUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'class="btn btn-success" '
            . 'style="background:#27ae60;border-color:#219a52;">'
            . '<i class="icon-tag"></i>&nbsp;' . $this->l('Manage Badges') . '</a>';
        $html .= '&nbsp;&nbsp;<span style="color:#4a7c59;font-style:italic;">'
            . $this->l('Create, edit and assign badges to products.') . '</span>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Table showing the saved config for every shop at a glance — placed at the bottom.
     * Active shop row is highlighted. "Configure" links update the form above.
     */
    private function renderConfigTable(array $shops, int $selectedShopId = 0): string
    {
        $configUrl = $this->context->link->getAdminLink('AdminModules')
            . '&configure=' . $this->name;

        $html  = '<div style="border-top:1px solid #c5e8f5;padding:0 0 4px;">';
        $html .= '<div style="background:#eaf7fb;padding:8px 18px;font-weight:bold;color:#1a8fa8;">'
            . '<i class="icon-list"></i>&nbsp;' . $this->l('Saved settings per store') . '</div>';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table" style="margin:0;">';
        $html .= '<thead><tr style="background:#f0fafd;">'
            . '<th>' . $this->l('Store') . '</th>'
            . '<th>' . $this->l('Enabled') . '</th>'
            . '<th>' . $this->l('Listings') . '</th>'
            . '<th>' . $this->l('Product page') . '</th>'
            . '<th>' . $this->l('Hide flags') . '</th>'
            . '<th>' . $this->l('Max badges') . '</th>'
            . '<th></th>'
            . '</tr></thead>';
        $html .= '<tbody>';

        foreach ($shops as $shop) {
            $sid = (int) $shop['id_shop'];
            $name = htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8');

            $enabled     = $this->getConfigValueForShop('PRODUCTBADGES_ENABLED', $sid);
            $showListing = $this->getConfigValueForShop('PRODUCTBADGES_SHOW_LISTING', $sid);
            $showProduct = $this->getConfigValueForShop('PRODUCTBADGES_SHOW_PRODUCT', $sid);
            $hideFlags   = $this->getConfigValueForShop('PRODUCTBADGES_HIDE_FLAGS', $sid);
            $maxBadges   = $this->getConfigValueForShop('PRODUCTBADGES_MAX_BADGES', $sid);

            $badge = static function (int $v): string {
                return $v
                    ? '<span class="label label-success" style="font-size:12px;">YES</span>'
                    : '<span class="label label-danger"  style="font-size:12px;">NO</span>';
            };

            $isActive = ($sid === $selectedShopId);
            $rowStyle = $isActive
                ? ' style="background:#d6f0f8;font-weight:bold;"'
                : '';
            $editUrl  = htmlspecialchars($configUrl . '&id_shop=' . $sid, ENT_QUOTES, 'UTF-8');
            $btnClass = $isActive ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';

            $html .= '<tr' . $rowStyle . '>'
                . '<td>' . $name . ($isActive ? ' &nbsp;<small style="color:#888;">▲ editing</small>' : '') . '</td>'
                . '<td>' . $badge($enabled) . '</td>'
                . '<td>' . $badge($showListing) . '</td>'
                . '<td>' . $badge($showProduct) . '</td>'
                . '<td>' . $badge($hideFlags) . '</td>'
                . '<td>' . (int) $maxBadges . '</td>'
                . '<td><a href="' . $editUrl . '" class="' . $btnClass . '">'
                . '<i class="icon-pencil"></i>&nbsp;' . $this->l('Configure') . '</a></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div></div>';

        return $html;
    }

    /**
     * Ensure a global config row (id_shop=0 or NULL) exists for every key.
     * Accepts both NULL and 0 conventions (PS uses 0 in multistore context).
     */
    private function ensureGlobalConfig(): void
    {
        $defaults = [
            'PRODUCTBADGES_ENABLED'      => 1,
            'PRODUCTBADGES_SHOW_LISTING' => 1,
            'PRODUCTBADGES_SHOW_PRODUCT' => 1,
            'PRODUCTBADGES_MAX_BADGES'   => 3,
            'PRODUCTBADGES_HIDE_FLAGS'   => 1,
        ];

        foreach ($defaults as $key => $default) {
            $safeKey = pSQL($key);

            $globalExists = $this->execQueryValue(
                'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration`
                 WHERE `name` = \'' . $safeKey . '\'
                   AND (`id_shop` IS NULL OR `id_shop` = 0)
                   AND (`id_shop_group` IS NULL OR `id_shop_group` = 0)
                 LIMIT 1'
            );

            if ($globalExists === null) {
                $anyVal = $this->execQueryValue(
                    'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration`
                     WHERE `name` = \'' . $safeKey . '\' LIMIT 1'
                );
                $this->writeConfigRow($key, $anyVal !== null ? $anyVal : $default, null);
            }
        }
    }

    /**
     * Save module config for a specific shop (or globally if $idShop is null).
     *
     * Direct DB writes bypass Configuration::updateValue() which auto-injects
     * the current shop context when id_shop is null — making it impossible to
     * write a true global row from within a shop context.
     *
     * @param int|null $idShop  null = global; >0 = specific shop record
     */
    private function saveConfig(?int $idShop): string
    {
        $enabled          = (int) Tools::getValue('PRODUCTBADGES_ENABLED');
        $showListing      = (int) Tools::getValue('PRODUCTBADGES_SHOW_LISTING');
        $showProduct      = (int) Tools::getValue('PRODUCTBADGES_SHOW_PRODUCT');
        $maxBadges        = (int) Tools::getValue('PRODUCTBADGES_MAX_BADGES');
        $hideDefaultFlags = (int) Tools::getValue('PRODUCTBADGES_HIDE_FLAGS');

        if ($maxBadges < 0 || $maxBadges > 20) {
            return $this->displayError($this->l('Max badges must be between 0 and 20.'));
        }

        $dbErrors = array_filter([
            $this->writeConfigRow('PRODUCTBADGES_ENABLED',      $enabled ? 1 : 0,          $idShop),
            $this->writeConfigRow('PRODUCTBADGES_SHOW_LISTING', $showListing ? 1 : 0,       $idShop),
            $this->writeConfigRow('PRODUCTBADGES_SHOW_PRODUCT', $showProduct ? 1 : 0,       $idShop),
            $this->writeConfigRow('PRODUCTBADGES_MAX_BADGES',   $maxBadges,                 $idShop),
            $this->writeConfigRow('PRODUCTBADGES_HIDE_FLAGS',   $hideDefaultFlags ? 1 : 0,  $idShop),
        ]);

        if (!empty($dbErrors)) {
            return $this->displayError('DB write error: ' . implode(' | ', $dbErrors));
        }

        // Bust PS config cache so the new values are visible immediately
        if (method_exists('Configuration', 'loadConfiguration')) {
            Configuration::loadConfiguration();
        }


        // Append store name to confirmation when saving per-shop
        $suffix = '';
        if ($idShop !== null) {
            foreach (Shop::getShops(true) as $shop) {
                if ((int) $shop['id_shop'] === $idShop) {
                    $suffix = ' — ' . htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8');
                    break;
                }
            }
        }

        return $this->displayConfirmation($this->l('Settings saved.') . $suffix);
    }

    /**
     * Low-level INSERT/UPDATE on ps_configuration using raw SQL via Db::execute().
     *
     * Uses Db::execute() (raw SQL) instead of Db::insert()/Db::update() to avoid
     * PS wrapper issues (double pSQL, silent failures).
     * Accepts both NULL and 0 conventions for id_shop/id_shop_group.
     * Inserts use id_shop_group=0 to match PS multistore convention.
     *
     * @param string   $key
     * @param mixed    $value
     * @param int|null $idShop  null|0 = global; >0 = specific shop
     */
    /**
     * @return string  empty on success; MySQL error message on failure
     */
    private function writeConfigRow(string $key, $value, ?int $idShop): string
    {
        $db       = Db::getInstance();
        $pfx      = _DB_PREFIX_;
        $safeKey  = pSQL($key);
        $safeVal  = pSQL((string) $value);
        $idShopDb = ($idShop !== null && $idShop > 0) ? (int) $idShop : 0;
        $now      = date('Y-m-d H:i:s');

        $shopCond = $idShopDb > 0
            ? '`id_shop` = ' . $idShopDb . ' AND (`id_shop_group` IS NULL OR `id_shop_group` = 0)'
            : '(`id_shop` IS NULL OR `id_shop` = 0) AND (`id_shop_group` IS NULL OR `id_shop_group` = 0)';

        $existingId = (int) $this->execQueryValue(
            'SELECT `id_configuration`
             FROM `' . $pfx . 'configuration`
             WHERE `name` = \'' . $safeKey . '\'
               AND ' . $shopCond . '
             ORDER BY `id_configuration` ASC
             LIMIT 1'
        );

        if ($existingId > 0) {
            $ok = $db->execute(
                'UPDATE `' . $pfx . 'configuration`
                 SET `value` = \'' . $safeVal . '\',
                     `date_upd` = \'' . $now . '\'
                 WHERE `id_configuration` = ' . $existingId
            );
        } else {
            $ok = $db->execute(
                'INSERT INTO `' . $pfx . 'configuration`
                 (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`)
                 VALUES (0, ' . $idShopDb . ', \'' . $safeKey . '\', \'' . $safeVal . '\',
                         \'' . $now . '\', \'' . $now . '\')'
            );
        }

        return $ok ? '' : ('[' . $key . '] ' . $db->getMsgError());
    }

    /**
     * @param int|null $idShop  Which shop's settings to display (null = global)
     */
    private function renderConfigForm(?int $idShop): string
    {
        // Include shop name in legend so it's clear which shop is being edited
        $shopLabel = '';
        if ($idShop !== null && Shop::isFeatureActive()) {
            foreach (Shop::getShops(true) as $shop) {
                if ((int) $shop['id_shop'] === $idShop) {
                    $shopLabel = ' — ' . htmlspecialchars($shop['name'], ENT_QUOTES, 'UTF-8');
                    break;
                }
            }
        }

        $fields = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings') . $shopLabel,
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Enable module'),
                        'name'    => 'PRODUCTBADGES_ENABLED',
                        'options' => $this->getYesNoOptions(),
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Show in product listings'),
                        'name'    => 'PRODUCTBADGES_SHOW_LISTING',
                        'options' => $this->getYesNoOptions(),
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Show on product page'),
                        'name'    => 'PRODUCTBADGES_SHOW_PRODUCT',
                        'options' => $this->getYesNoOptions(),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Maximum badges per product'),
                        'name'  => 'PRODUCTBADGES_MAX_BADGES',
                        'class' => 'fixed-width-sm',
                        'desc'  => $this->l('Set 0 for unlimited.'),
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Hide default theme badges (New, Sale, etc.)'),
                        'name'    => 'PRODUCTBADGES_HIDE_FLAGS',
                        'desc'    => $this->l('Hides .product-flags elements added by the theme.'),
                        'options' => $this->getYesNoOptions(),
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
        // Embed id_shop in the form action URL so it survives the POST roundtrip
        $helper->currentIndex             = AdminController::$currentIndex . '&configure=' . $this->name
            . ($idShop !== null ? '&id_shop=' . $idShop : '');
        $helper->default_form_language    = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->show_toolbar             = false;
        $helper->submit_action            = 'submitProductBadgesConfig';

        // Read values for the selected shop (with global fallback via cascade)
        $helper->fields_value['PRODUCTBADGES_ENABLED']      = $this->getConfigValueForShop('PRODUCTBADGES_ENABLED', $idShop);
        $helper->fields_value['PRODUCTBADGES_SHOW_LISTING'] = $this->getConfigValueForShop('PRODUCTBADGES_SHOW_LISTING', $idShop);
        $helper->fields_value['PRODUCTBADGES_SHOW_PRODUCT'] = $this->getConfigValueForShop('PRODUCTBADGES_SHOW_PRODUCT', $idShop);
        $helper->fields_value['PRODUCTBADGES_MAX_BADGES']   = $this->getConfigValueForShop('PRODUCTBADGES_MAX_BADGES', $idShop);
        $helper->fields_value['PRODUCTBADGES_HIDE_FLAGS']   = $this->getConfigValueForShop('PRODUCTBADGES_HIDE_FLAGS', $idShop);

        return $helper->generateForm([$fields]);
    }

    /**
     * Options array for a Yes/No <select> in HelperForm.
     * More reliable than 'switch' type which depends on PS prestashop-switch JS.
     */
    private function getYesNoOptions(): array
    {
        return [
            'query' => [
                ['val' => 1, 'label' => $this->l('Yes')],
                ['val' => 0, 'label' => $this->l('No')],
            ],
            'id'   => 'val',
            'name' => 'label',
        ];
    }

    // ─── Front-office hooks ──────────────────────────────────────────────────

    public function hookDisplayHeader(): string
    {
        if (!$this->getConfigValue('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        $this->context->controller->addCSS($this->_path . 'views/css/productbadgesMultiStorev1.css');
        $this->context->controller->addJS($this->_path . 'views/js/productbadgesMultiStorev1.js');

        $idLang      = (int) $this->context->language->id;
        $assignments = ProductBadge::getAllAssignments($idLang);
        $maxBadges   = $this->getConfigValue('PRODUCTBADGES_MAX_BADGES');

        $output = '<div id="productbadges-data"'
            . ' data-badges="' . htmlspecialchars(json_encode($assignments), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-max="' . (int) $maxBadges . '"'
            . ' style="display:none"></div>';

        if ($this->getConfigValue('PRODUCTBADGES_HIDE_FLAGS')) {
            $output .= '<style>.product-flags{display:none!important}</style>';
        }

        return $output;
    }

    public function hookActionAdminControllerSetMedia(): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/productbadges-adminMultiStorev1.css');
        $this->context->controller->addJS($this->_path . 'views/js/productbadges-adminMultiStorev1.js');
    }

    public function hookDisplayProductListItem(array $params): string
    {
        if (!$this->getConfigValue('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        if (!$this->getConfigValue('PRODUCTBADGES_SHOW_LISTING')) {
            return '';
        }

        $idProduct = (int) ($params['product']['id_product']
            ?? $params['product']['id']
            ?? 0);

        return $this->renderBadgesForProduct($idProduct);
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!$this->getConfigValue('PRODUCTBADGES_ENABLED')) {
            return '';
        }

        if (!$this->getConfigValue('PRODUCTBADGES_SHOW_PRODUCT')) {
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

        $maxBadges = $this->getConfigValue('PRODUCTBADGES_MAX_BADGES');
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

        return $this->display(__FILE__, 'views/templates/hook/badgesMultiStorev1.tpl');
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
