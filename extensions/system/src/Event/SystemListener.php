<?php

namespace Pagekit\System\Event;

use Pagekit\Application as App;
use Pagekit\Finder\Event\FileAccessEvent;
use Pagekit\Menu\Event\MenuEvent;
use Pagekit\Menu\Model\Menu;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemListener implements EventSubscriberInterface
{
    /**
     * Dispatches the 'system.site' or 'system.admin' event.
     */
    public function onSystemLoaded($event)
    {
        App::trigger(App::isAdmin() ? 'system.admin' : 'system.site', $event);
    }

    /**
     * Creates the menu instance and dispatches the 'system.admin_menu' event.
     */
    public function onSystemAdmin()
    {
        $menu = new Menu;
        $menu->setId('admin');

        App::menus()->registerFilter('access', 'Pagekit\System\Menu\Filter\AccessFilter', 16);
        App::menus()->registerFilter('active', 'Pagekit\System\Menu\Filter\ActiveFilter');

        App::trigger('system.admin_menu', new MenuEvent($menu));

        App::set('admin.menu', App::menus()->getTree($menu, ['access' => true]));
    }

    /**
     * Registers links.
     *
     * @param LinkEvent $event
     */
    public function onSystemLink(LinkEvent $event)
    {
        $event->register('Pagekit\System\Link\System');
    }

    /**
     * Registers templates.
     *
     * @param TmplEvent $event
     */
    public function onSystemTmpl(TmplEvent $event)
    {
        $event->register('linkpicker.modal', 'extensions/system/views/tmpl/linkpicker.modal.razr');
        $event->register('linkpicker.replace', 'extensions/system/views/tmpl/linkpicker.replace.razr');
        $event->register('marketplace.details', 'extensions/system/views/tmpl/marketplace.details.razr');
        $event->register('marketplace.table', 'extensions/system/views/tmpl/marketplace.table.razr');
        $event->register('package.updates', 'extensions/system/views/tmpl/package.updates.razr');
        $event->register('package.upload', 'extensions/system/views/tmpl/package.upload.razr');
    }

    /**
     * Registers the media storage folder
     *
     * @param FileAccessEvent $event
     */
    public function onSystemFinder(FileAccessEvent $event)
    {
        if (App::user()->hasAccess('system: manage storage | system: manage storage read only')) {
            $event->path('#^'.strtr(App::get('path.storage'), '\\', '/').'($|\/.*)#', App::user()->hasAccess('system: manage storage') ? 'w' : 'r');
        }
    }

    /**
     * Triggers the system.loaded event, after the request was matched.
     */
    public function onRequestMatched($event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        App::trigger('system.loaded', $event);
    }

    /**
     * Add system settings screens.
     */
    public function onSettingsEdit($event, $config)
    {
        $event->config('system', $config, ['storage']);
        $event->options('system', App::system()->config, ['api.key', 'release_channel', 'site_title', 'site_description', 'maintenance.enabled', 'maintenance.msg']);
        $event->data('sqlite', class_exists('SQLite3') || (class_exists('PDO') && in_array('sqlite', \PDO::getAvailableDrivers(), true)));
        $event->view('site',   __('Site'),   App::tmpl('extensions/system/views/admin/settings/site.php'));
        $event->view('system', __('System'), App::tmpl('extensions/system/views/admin/settings/system.php'));
    }

    /**
     * Add system settings screens.
     */
    public function onSettingsSave($event, $config)
    {
        if ($config['framework.debug'] != App::module('framework')->config('debug')) {
            App::module('system/cache')->clearCache();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'system.admin'         => 'onSystemAdmin',
            'system.finder'        => 'onSystemFinder',
            'system.link'          => 'onSystemLink',
            'system.loaded'        => 'onSystemLoaded',
            'system.tmpl'          => 'onSystemTmpl',
            'kernel.request'       => 'onRequestMatched',
            'system.settings.edit' => ['onSettingsEdit', 8],
            'system.settings.save' => 'onSettingsSave'
        ];
    }
}
