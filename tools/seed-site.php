<?php

/**
 * Prepare a Joomla site for an import: a menu heading to import under, and the
 * component's options pointing at it.
 *
 *   php tools/seed-site.php                 # ./joomla
 *   php tools/seed-site.php ../some/site
 *
 * A fresh site has a Main Menu with Home in it and nothing to hang a manual
 * under, so the import screen correctly refuses to start. This makes a "Help"
 * heading in the Main Menu (once - a second run finds it) and sets it as the
 * Parent Menu Item. Every other option keeps its default.
 *
 * Through the Menu table's own nested-set methods rather than SQL: lft and rgt
 * written by hand are how a menu tree gets corrupted.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}.\n");
    exit(2);
}

\defined('_JEXEC') || \define('_JEXEC', 1);
\define('JPATH_BASE', $site);

require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'defines.php';
require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'framework.php';

$container = Joomla\CMS\Factory::getContainer();

$container->alias('session', 'session.cli')
    ->alias(Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\SessionInterface::class, 'session.cli');

Joomla\CMS\Factory::$application = $container->get(Joomla\Console\Application::class);

$db = $container->get(Joomla\Database\DatabaseInterface::class);

// --- The menu heading ---------------------------------------------------------

$existing = (int) $db->setQuery(
    $db->createQuery()
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('menutype') . ' = ' . $db->quote('mainmenu'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('help'))
        ->where($db->quoteName('parent_id') . ' = 1')
)->loadResult();

if ($existing > 0) {
    $headingId = $existing;
    printf("menu heading Help exists (id %d)\n", $headingId);
} else {
    $table = new Joomla\CMS\Table\Menu($db, $container->get(Joomla\Event\DispatcherInterface::class));
    $table->setLocation(1, 'last-child');

    $ok = $table->bind([
        'menutype'          => 'mainmenu',
        'title'             => 'Help',
        'alias'             => 'help',
        'type'              => 'heading',
        'link'              => '',
        'published'         => 1,
        'parent_id'         => 1,
        'level'             => 1,
        'component_id'      => 0,
        'access'            => 1,
        'language'          => '*',
        'client_id'         => 0,
        'params'            => '{}',
        'img'               => '',
        'template_style_id' => 0,
        'browserNav'        => 0,
        'home'              => 0,
    ]) && $table->check() && $table->store() && $table->rebuildPath($table->id);

    if (!$ok) {
        fwrite(STDERR, 'Cannot make the menu heading: ' . $table->getError() . "\n");
        exit(1);
    }

    $headingId = (int) $table->id;
    printf("menu heading Help made (id %d)\n", $headingId);
}

// --- The component's options --------------------------------------------------

$query = $db->createQuery()
    ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('com_mdimport'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

$extension = $db->setQuery($query)->loadObject();

if ($extension === null) {
    fwrite(STDERR, "com_mdimport is not installed. Run `composer install-local` first.\n");
    exit(1);
}

$params                     = json_decode((string) $extension->params, true) ?: [];
$params['parent_menu_item'] = $headingId;

$row = (object) [
    'extension_id' => (int) $extension->extension_id,
    'params'       => json_encode($params),
];

$db->updateObject('#__extensions', $row, 'extension_id');

printf("com_mdimport imports under menu item %d\n", $headingId);
