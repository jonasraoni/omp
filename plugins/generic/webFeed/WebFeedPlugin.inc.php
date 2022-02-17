<?php

/**
 * @file plugins/generic/webFeed/WebFeedPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedPlugin
 * @ingroup plugins_block_webFeed
 *
 * @brief Web Feeds plugin class
 */

use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

use PKP\plugins\GenericPlugin;

class WebFeedPlugin extends GenericPlugin
{
    /**
     * Get the display name of this plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.webfeed.displayName');
    }

    /**
     * Get the description of this plugin
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.webfeed.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                HookRegistry::register('TemplateManager::display', [$this, 'callbackAddLinks']);
                $this->import('WebFeedBlockPlugin');
                $blockPlugin = new WebFeedBlockPlugin($this);
                PluginRegistry::register('blocks', $blockPlugin, $this->getPluginPath());

                $this->import('WebFeedGatewayPlugin');
                $gatewayPlugin = new WebFeedGatewayPlugin($this);
                PluginRegistry::register('gateways', $gatewayPlugin, $this->getPluginPath());
            }
            return true;
        }
        return false;
    }

    /**
     * Get the name of the settings file to be installed on new context
     * creation.
     *
     * @return string
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Add feed links to page <head> on select/all pages.
     */
    public function callbackAddLinks($hookName, $args)
    {
        // Only page requests will be handled
        $request = Application::get()->getRequest();
        if (!is_a($request->getRouter(), 'PKPPageRouter')) {
            return false;
        }

        $templateManager = & $args[0];
        $currentPress = $templateManager->getTemplateVars('currentPress');
        $displayPage = $this->getSetting($currentPress->getId(), 'displayPage');

        // Define when the <link> elements should appear
        $contexts = $displayPage == 'homepage' ? 'frontend-index' : 'frontend';

        $templateManager->addHeader(
            'webFeedAtom+xml',
            '<link rel="alternate" type="application/atom+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'atom']) . '">',
            [
                'contexts' => $contexts,
            ]
        );
        $templateManager->addHeader(
            'webFeedRdf+xml',
            '<link rel="alternate" type="application/rdf+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'rss']) . '">',
            [
                'contexts' => $contexts,
            ]
        );
        $templateManager->addHeader(
            'webFeedRss+xml',
            '<link rel="alternate" type="application/rss+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'rss2']) . '">',
            [
                'contexts' => $contexts,
            ]
        );

        return false;
    }

    /**
     * @see Plugin::getActions()
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * @see Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('function', 'plugin_url', [$this, 'smartyPluginUrl']);

                $this->import('SettingsForm');
                $form = new SettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }
}
