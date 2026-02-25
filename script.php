<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Filesystem\File;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

class com_contentbuilder_ngInstallerScript extends InstallerScript
{
  private const LEGACY_TABLE_RENAMES = [
    'contentbuilder_articles' => 'contentbuilder_ng_articles',
    'contentbuilder_elements' => 'contentbuilder_ng_elements',
    'contentbuilder_forms' => 'contentbuilder_ng_forms',
    'contentbuilder_list_records' => 'contentbuilder_ng_list_records',
    'contentbuilder_list_states' => 'contentbuilder_ng_list_states',
    'contentbuilder_rating_cache' => 'contentbuilder_ng_rating_cache',
    'contentbuilder_records' => 'contentbuilder_ng_records',
    'contentbuilder_registered_users' => 'contentbuilder_ng_registered_users',
    'contentbuilder_resource_access' => 'contentbuilder_ng_resource_access',
    'contentbuilder_storage_fields' => 'contentbuilder_ng_storage_fields',
    'contentbuilder_storages' => 'contentbuilder_ng_storages',
    'contentbuilder_users' => 'contentbuilder_ng_users',
    'contentbuilder_verifications' => 'contentbuilder_ng_verifications',
  ];
  protected $minimumPhp = '8.1';
  protected $minimumJoomla = '5.0';

  public function __construct()
  {
    // Logger personnalisé
    $logPath = Factory::getApplication()->getConfig()->get('log_path') ?: JPATH_ROOT . '/logs';
    if (!Folder::exists($logPath)) {
      Folder::create($logPath);
    }

    Log::addLogger(
      [
        'text_file' => 'contentbuilder_ng_install.log',
        'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}',
        'text_file_path'     => $logPath,
      ],
      Log::ALL,
      ['com_contentbuilder_ng.install']
    );


    // Starting logs.
    $this->writeInstallLogEntry('---------------------------------------------------------', Log::INFO);
    $this->writeInstallLogEntry('[OK] ContentBuilder NG installation/update started.', Log::INFO);
    $this->writeInstallLogEntry('* PHP Version: ' . PHP_VERSION . '.', Log::INFO);
    $this->writeInstallLogEntry('* Joomla Version : ' . JVERSION . '.', Log::INFO);
    $this->writeInstallLogEntry('* User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . '.', Log::INFO);
  }

  function getPlugins()
  {
    $plugins = array();
    $plugins['contentbuilder_ng_verify'] = array();
    $plugins['contentbuilder_ng_verify'][] = 'paypal';
    $plugins['contentbuilder_ng_verify'][] = 'passthrough';
    $plugins['contentbuilder_ng_validation'] = array();
    $plugins['contentbuilder_ng_validation'][] = 'notempty';
    $plugins['contentbuilder_ng_validation'][] = 'equal';
    $plugins['contentbuilder_ng_validation'][] = 'email';
    $plugins['contentbuilder_ng_validation'][] = 'date_not_before';
    $plugins['contentbuilder_ng_validation'][] = 'date_is_valid';
    $plugins['contentbuilder_ng_themes'] = array();
    $plugins['contentbuilder_ng_themes'][] = 'khepri';
    $plugins['contentbuilder_ng_themes'][] = 'blank';
    $plugins['contentbuilder_ng_themes'][] = 'joomla6';
    $plugins['contentbuilder_ng_themes'][] = 'dark';
    $plugins['system'] = array();
    $plugins['system'][] = 'contentbuilder_ng_system';
    $plugins['contentbuilder_ng_submit'] = array();
    $plugins['contentbuilder_ng_submit'][] = 'submit_sample';
    $plugins['contentbuilder_ng_listaction'] = array();
    $plugins['contentbuilder_ng_listaction'][] = 'trash';
    $plugins['contentbuilder_ng_listaction'][] = 'untrash';
    $plugins['content'] = array();
    $plugins['content'][] = 'contentbuilder_ng_verify';
    $plugins['content'][] = 'contentbuilder_ng_permission_observer';
    $plugins['content'][] = 'contentbuilder_ng_image_scale';
    $plugins['content'][] = 'contentbuilder_ng_download';
    $plugins['content'][] = 'contentbuilder_ng_rating';
    return $plugins;
  }

  private function resolveJoomlaTimezoneName(): string
  {
    $timezoneName = '';

    try {
      $app = Factory::getApplication();
      if (is_object($app) && method_exists($app, 'get')) {
        $timezoneName = trim((string) $app->get('offset', ''));
      }
    } catch (\Throwable) {
      // Ignore and fallback below.
    }

    if ($timezoneName === '') {
      try {
        $timezoneName = trim((string) Factory::getApplication()->getConfig()->get('offset', ''));
      } catch (\Throwable) {
        $timezoneName = '';
      }
    }

    if ($timezoneName === '') {
      $timezoneName = 'UTC';
    }

    try {
      new \DateTimeZone($timezoneName);
    } catch (\Throwable) {
      $timezoneName = 'UTC';
    }

    return $timezoneName;
  }

  private function writeInstallLogEntry(string $message, int $priority = Log::INFO): void
  {
    $previousTimezone = date_default_timezone_get();
    $targetTimezone = $this->resolveJoomlaTimezoneName();
    $switchedTimezone = false;

    if ($previousTimezone !== $targetTimezone) {
      $switchedTimezone = (bool) @date_default_timezone_set($targetTimezone);
    }

    try {
      Log::add($message, $priority, 'com_contentbuilder_ng.install');
    } finally {
      if ($switchedTimezone) {
        @date_default_timezone_set($previousTimezone);
      }
    }
  }

  private function formatInstallMessageForDisplay(string $message): string
  {
    return str_replace(
      '[OK]',
      '<span style="color:#198754;font-weight:700;" aria-hidden="true">&#10003;</span>',
      $message
    );
  }

  private function log(string $message, int $priority = Log::INFO, bool $enqueue = true): void
  {
    $this->writeInstallLogEntry($message, $priority);

    if (!$enqueue) {
      return;
    }

    $app = Factory::getApplication();
    $type = match ($priority) {
      Log::ERROR => 'error',
      Log::WARNING => 'warning',
      default => 'message',
    };

    $app->enqueueMessage($this->formatInstallMessageForDisplay($message), $type);
  }

  private function getIndexedColumns(DatabaseInterface $db, string $tableQN): array
  {
    $indexedColumns = [];

    try {
      $db->setQuery('SHOW INDEX FROM ' . $tableQN);
      $indexRows = $db->loadAssocList() ?: [];

      foreach ($indexRows as $indexRow) {
        $columnName = strtolower((string) ($indexRow['Column_name'] ?? $indexRow['column_name'] ?? ''));
        if ($columnName !== '') {
          $indexedColumns[$columnName] = true;
        }
      }
    } catch (\Throwable $e) {
      $this->log(
        '[WARNING] Could not inspect existing indexes on ' . $tableQN . ': ' . $e->getMessage(),
        Log::WARNING
      );
    }

    return $indexedColumns;
  }

  private function getTableIndexes(DatabaseInterface $db, string $tableQN): array
  {
    $indexMap = [];

    $db->setQuery('SHOW INDEX FROM ' . $tableQN);
    $rows = $db->loadAssocList() ?: [];

    foreach ($rows as $row) {
      $keyName = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');

      if ($keyName === '') {
        continue;
      }

      $seqInIndex = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 0);
      if ($seqInIndex < 1) {
        $seqInIndex = count($indexMap[$keyName]['columns'] ?? []) + 1;
      }

      $columnName = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
      if ($columnName === '') {
        $columnName = strtolower(trim((string) ($row['Expression'] ?? $row['expression'] ?? '')));
      }

      if ($columnName === '') {
        continue;
      }

      if (!isset($indexMap[$keyName])) {
        $indexMap[$keyName] = [
          'non_unique' => (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1),
          'index_type' => strtoupper((string) ($row['Index_type'] ?? $row['index_type'] ?? 'BTREE')),
          'columns' => [],
        ];
      }

      $indexMap[$keyName]['columns'][$seqInIndex] = [
        'name' => $columnName,
        'sub_part' => (string) ($row['Sub_part'] ?? $row['sub_part'] ?? ''),
        'collation' => strtoupper((string) ($row['Collation'] ?? $row['collation'] ?? 'A')),
      ];
    }

    foreach ($indexMap as &$indexDefinition) {
      ksort($indexDefinition['columns'], SORT_NUMERIC);
      $indexDefinition['columns'] = array_values($indexDefinition['columns']);
      $indexDefinition['signature'] = $this->indexDefinitionSignature($indexDefinition);
    }
    unset($indexDefinition);

    return $indexMap;
  }

  private function indexDefinitionSignature(array $indexDefinition): string
  {
    $columnParts = [];

    foreach ((array) ($indexDefinition['columns'] ?? []) as $columnDefinition) {
      $columnParts[] = implode(':', [
        (string) ($columnDefinition['name'] ?? ''),
        (string) ($columnDefinition['sub_part'] ?? ''),
        (string) ($columnDefinition['collation'] ?? ''),
      ]);
    }

    return implode('|', [
      (string) ($indexDefinition['non_unique'] ?? 1),
      strtoupper((string) ($indexDefinition['index_type'] ?? 'BTREE')),
      implode(',', $columnParts),
    ]);
  }

  private function removeDuplicateIndexes(DatabaseInterface $db, string $tableQN, string $tableAlias, int $storageId = 0): int
  {
    $removedIndexes = 0;

    try {
      $tableIndexes = $this->getTableIndexes($db, $tableQN);
    } catch (\Throwable $e) {
      $this->log(
        "[WARNING] Could not inspect indexes on {$tableAlias} for duplicate cleanup: " . $e->getMessage(),
        Log::WARNING
      );

      return 0;
    }

    if ($tableIndexes === []) {
      return 0;
    }

    $signatures = [];

    foreach ($tableIndexes as $keyName => $indexDefinition) {
      if (strtoupper((string) $keyName) === 'PRIMARY') {
        continue;
      }

      $signature = (string) ($indexDefinition['signature'] ?? '');
      if ($signature === '') {
        continue;
      }

      $signatures[$signature][] = (string) $keyName;
    }

    foreach ($signatures as $duplicateNames) {
      if (count($duplicateNames) < 2) {
        continue;
      }

      usort(
        $duplicateNames,
        static fn(string $a, string $b): int => strcasecmp($a, $b)
      );

      $keptIndexName = array_shift($duplicateNames);

      foreach ($duplicateNames as $duplicateName) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $tableQN
            . ' DROP INDEX ' . $db->quoteName($duplicateName)
          )->execute();

          $removedIndexes++;
          $storageSuffix = $storageId > 0 ? " (storage {$storageId})" : '';
          $this->log("[OK] Removed duplicate index {$duplicateName} on {$tableAlias}{$storageSuffix}; kept {$keptIndexName}.");
        } catch (\Throwable $e) {
          $storageSuffix = $storageId > 0 ? " (storage {$storageId})" : '';
          $this->log(
            "[WARNING] Failed removing duplicate index {$duplicateName} on {$tableAlias}{$storageSuffix}: " . $e->getMessage(),
            Log::WARNING
          );
        }
      }
    }

    return $removedIndexes;
  }

  private function getCurrentInstalledVersion(): string
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $query = $db->getQuery(true)
      ->select($db->quoteName('manifest_cache'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilder_ng'));

    $db->setQuery($query);
    $manifest = $db->loadResult();

    if ($manifest) {
      $manifest = json_decode($manifest, true);
      $version = $manifest['version'] ?? '0.0.0';
    } else {
      $version = '0.0.0';
    }

    return $version;
  }

  private function getIncomingPackageVersion(?InstallerAdapter $parent): string
  {
    if ($parent) {
      try {
        if (method_exists($parent, 'getManifest')) {
          $manifest = $parent->getManifest();
          if ($manifest instanceof \SimpleXMLElement) {
            $version = trim((string) ($manifest->version ?? ''));
            if ($version !== '') {
              return $version;
            }

            $attrVersion = trim((string) ($manifest['version'] ?? ''));
            if ($attrVersion !== '') {
              return $attrVersion;
            }
          }
        }
      } catch (\Throwable) {
        // Ignore and fallback below.
      }
    }

    return 'unknown';
  }

  private function getInstallerPackageInfo($parent): array
  {
    $candidates = [];
    $pushCandidate = static function (array &$list, $path): void {
      $path = trim((string) ($path ?? ''));

      if ($path === '') {
        return;
      }

      if (!in_array($path, $list, true)) {
        $list[] = $path;
      }
    };

    if (is_object($parent)) {
      if (method_exists($parent, 'getPath')) {
        foreach (['packagefile', 'package', 'archive', 'source', 'manifest'] as $key) {
          try {
            $pushCandidate($candidates, $parent->getPath($key));
          } catch (\Throwable) {
            // Ignore path accessor issues.
          }
        }
      }

      if (method_exists($parent, 'getParent')) {
        try {
          $parentInstaller = $parent->getParent();
        } catch (\Throwable) {
          $parentInstaller = null;
        }

        if (is_object($parentInstaller) && method_exists($parentInstaller, 'getPath')) {
          foreach (['packagefile', 'package', 'archive', 'source', 'manifest'] as $key) {
            try {
              $pushCandidate($candidates, $parentInstaller->getPath($key));
            } catch (\Throwable) {
              // Ignore path accessor issues.
            }
          }
        }
      }
    }

    $installerName = trim((string) ($_FILES['install_package']['name'] ?? ''));
    $sizeBytes = null;

    if (isset($_FILES['install_package']['size']) && is_numeric($_FILES['install_package']['size'])) {
      $sizeBytes = (int) $_FILES['install_package']['size'];
    }

    $selectedPath = null;

    foreach ($candidates as $candidate) {
      if (is_dir($candidate)) {
        continue;
      }

      if (!is_file($candidate)) {
        continue;
      }

      $filename = strtolower(basename($candidate));
      if (
        str_ends_with($filename, '.zip')
        || str_ends_with($filename, '.tar')
        || str_ends_with($filename, '.tar.gz')
        || str_ends_with($filename, '.tgz')
      ) {
        $selectedPath = $candidate;
        break;
      }

      if ($selectedPath === null) {
        $selectedPath = $candidate;
      }
    }

    if ($selectedPath !== null && is_file($selectedPath)) {
      if ($installerName === '') {
        $installerName = basename($selectedPath);
      }

      if ($sizeBytes === null) {
        $size = filesize($selectedPath);
        if ($size !== false) {
          $sizeBytes = (int) $size;
        }
      }
    }

    if ($installerName === '') {
      $installerName = 'unknown';
    }

    return [
      'name' => $installerName,
      'size_bytes' => $sizeBytes,
    ];
  }

  private function formatBytesForLog(?int $bytes): string
  {
    if ($bytes === null || $bytes < 0) {
      return 'unknown';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
      $value /= 1024;
      $unitIndex++;
    }

    $formatted = $unitIndex === 0
      ? (string) (int) $value
      : number_format($value, 2, '.', '');

    return $formatted . ' ' . $units[$unitIndex] . ' (' . $bytes . ' bytes)';
  }


  function installAndUpdate(): bool
  {
    /*    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $plugins = $this->getPlugins();
    $base_path = JPATH_SITE . '/administrator/components/com_contentbuilder_ng/plugins';
    $folders = Folder::folders($base_path);

    foreach ($folders as $folder) {
      $installer = new Installer();
      $installer->setDatabase(\Joomla\CMS\Factory::getContainer()->get('DatabaseDriver'));

      Factory::getApplication()->enqueueMessage('Installing plugin <b>' . $folder . '</b>', 'message');
      $success = $installer->install($base_path . '/' . $folder);
      if (!$success) {
        Factory::getApplication()->enqueueMessage('Install failed for plugin <b>' . $folder . '</b>', 'error');
      }
    }*/

    // Publication des plugins.
    /*
    foreach ($plugins as $folder => $subplugs) {
      foreach ($subplugs as $plugin) {
        $query = 'UPDATE #__extensions SET `enabled` = 1 WHERE `type` = "plugin" AND `element` = ' . $db->quote($plugin) . ' AND `folder` = ' . $db->quote($folder);
        $db->setQuery($query);
        $db->execute();
        $this->log("Plugin {$plugin} in folder {$folder} enabled.");
        Factory::getApplication()->enqueueMessage('Published plugin <b>' . $plugin . '</b>', 'message');
      }
    }*/

    $this->ensureUploadDirectoryExists();

    return true;
  }

  private function ensureUploadDirectoryExists(): void
  {
    $uploadDir = JPATH_ROOT . '/media/com_contentbuilder_ng/upload';
    $parentDir = dirname($uploadDir);

    if (!Folder::exists($parentDir) && !Folder::create($parentDir)) {
      $this->log('[WARNING] Could not create upload parent directory: ' . $parentDir, Log::WARNING);
      return;
    }

    if (!Folder::exists($uploadDir) && !Folder::create($uploadDir)) {
      $this->log('[WARNING] Could not create upload directory: ' . $uploadDir, Log::WARNING);
      return;
    }

    $indexFile = $uploadDir . '/index.html';
    if (!File::exists($indexFile)) {
      File::write($indexFile, '');
    }
  }

  /**
   * method to install the component
   *
   * @return bool
   */
  public function install(InstallerAdapter $parent): bool
  {
    if (!version_compare(PHP_VERSION, '8.1', '>=')) {
      Factory::getApplication()->enqueueMessage('"WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1, SORRY BUT YOU BETTER UNINSTALL THIS COMPONENT NOW!"', 'error');
    }


    return $this->installAndUpdate();
  }

  /**
   * method to update the component
   *
   * @return bool
   */
  public function update(InstallerAdapter $parent): bool
  {
    if (!version_compare(PHP_VERSION, '8.1', '>=')) {
      Factory::getApplication()->enqueueMessage('"WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1, SORRY BUT YOU BETTER UNINSTALL THIS COMPONENT NOW!"', 'error');
    }

    return $this->installAndUpdate();
  }

  /**
   * method to uninstall the component
   *
   * @return bool
   */
  public function uninstall(InstallerAdapter $parent): bool
  {
    $this->log('Uninstall of ContentBuilder_ng.');

    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $conditions = array_merge(
        $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilder_ng'),
        $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilder')
      );

      $db->setQuery(
        $db->getQuery(true)
          ->delete($db->quoteName('#__menu'))
          ->where('(' . implode(' OR ', $conditions) . ')')
      )->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to remove component menu entries on uninstall: ' . $e->getMessage(), Log::WARNING);
    }

    $plugins = $this->getPlugins();
    $installer = new Installer();
    $installer->setDatabase(\Joomla\CMS\Factory::getContainer()->get('DatabaseDriver'));

    foreach ($plugins as $folder => $subplugs) {
      foreach ($subplugs as $plugin) {
        $query = 'SELECT `extension_id` FROM #__extensions WHERE `type` = "plugin" AND `element` = ' . $db->quote($plugin) . ' AND `folder` = ' . $db->quote($folder);
        $db->setQuery($query);
        $id = $db->loadResult();

        if ($id) {
          $installer->uninstall('plugin', $id, 1);
        }
      }
    }

    $db->setQuery("SELECT id FROM `#__menu` WHERE `alias` = 'root'");
    if (!$db->loadResult()) {
      $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, (SELECT MAX(mlft.rgt)+1 FROM #__menu AS mlft), 0, '*', 0)");
      $db->execute();
    }

    return true;
  }

  /**
   * method to run before an install/update/uninstall method
   *
   * @return bool
   */
  public function preflight($type, $parent): bool
  {

    if (!parent::preflight($type, $parent)) {
      return false;
    }

    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $incomingVersion = $this->getIncomingPackageVersion($parent);
    $installerInfo = $this->getInstallerPackageInfo($parent);
    $timezoneName = (string) Factory::getApplication()->get('offset', '');
    if ($timezoneName === '') {
      $timezoneName = (string) Factory::getApplication()->getConfig()->get('offset', 'UTC');
    }

    try {
      $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
    } catch (\Throwable) {
      $timezone = new \DateTimeZone('UTC');
    }

    $startedAt = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s T');

    // === LOG POUR DÉBOGAGE ===
    $this->log(
      '[OK] Préflight : '
      . $startedAt
      . ' | Installateur : '
      . $installerInfo['name']
      . ' | Taille : '
      . $this->formatBytesForLog($installerInfo['size_bytes'] ?? null)
      . '.'
    );
    $incomingVersionHtml = htmlspecialchars($incomingVersion, ENT_QUOTES, 'UTF-8');
    $this->log('[OK] ContentBuilder NG Version <strong>' . $incomingVersionHtml . '</strong>.');
    $this->log('Preflight installation method call, parameter : ' . $type . '.');
    $this->log('[OK] Detected current version in manifest_cache : ' . $this->getCurrentInstalledVersion() . '.');

    $db->setQuery("Select id From `#__menu` Where `alias` = 'root'");
    if (!$db->loadResult()) {
      $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ), 0, '*', 0)");
      $db->execute();
    }

    if ($type === 'update') {
      $this->disableLegacyContentbuilderPlugins('preflight');
    }

    if ($type !== 'uninstall') {
      $this->renameLegacyTables();
      $this->migrateLegacyContentbuilderName();
    }

    return true;
  }


  /**
   * method to remove old librairies and files.
   *
   * @return void
   */
  private function removeOldLibraries(): void
  {
    $paths = [
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder/',
      JPATH_SITE . '/components/com_contentbuilder/',
      JPATH_ROOT . '/media/contentbuilder/',
      JPATH_SITE . '/media/com_contentbuilder/',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/classes/PHPExcel',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/classes/PHPExcel.php',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/librairies/PhpSpreadsheet',
    ];

    $app = Factory::getApplication();

    foreach ($paths as $path) {
      if (Folder::exists($path)) {
        if (Folder::delete($path)) {
          $this->log("[OK] Old {$path} folder successfully deleted.");
        } else {
          $this->log("[ERROR] Failed to delete {$path} folder.", Log::ERROR);
        }
      } elseif (File::exists($path)) {
        if (File::delete($path)) {
          $this->log("[OK] Old {$path} file successfully deleted.");
        } else {
          $this->log("[ERROR] Failed to delete {$path} file.", Log::ERROR);
        }
      } else {
        $this->log("[OK] No previous {$path} found.", Log::INFO, false);
      }
    }
  }

  private function removeObsoleteFiles(): void
  {
    $paths = [
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/src/Model/EditModel.php',
    ];

    $app = Factory::getApplication();

    foreach ($paths as $path) {
      if (File::exists($path)) {
        if (File::delete($path)) {
          $this->log("[OK] Removed obsolete file {$path}.");
        } else {
          $this->log("[ERROR] Failed to remove obsolete file {$path}.", Log::ERROR);
        }
      } else {
        $this->log("[OK] Obsolete file {$path} not found.", Log::INFO, false);
      }
    }
  }

  private function ensureMediaListTemplateInstalled(): void
  {
    $source = JPATH_SITE . '/components/com_contentbuilder_ng/tmpl/list/default.php';
    $target = JPATH_ROOT . '/media/com_contentbuilder_ng/images/list/tmpl/default.php';
    $targetDir = \dirname($target);

    if (File::exists($target)) {
      return;
    }

    if (!File::exists($source)) {
      $this->log("[WARNING] Missing source list template {$source}; cannot install media list template.", Log::WARNING);
      return;
    }

    if (!Folder::exists($targetDir) && !Folder::create($targetDir)) {
      $this->log("[WARNING] Could not create media template directory {$targetDir}.", Log::WARNING);
      return;
    }

    if (!File::copy($source, $target)) {
      $this->log("[WARNING] Could not install media list template {$target}.", Log::WARNING);
      return;
    }

    $this->log('[OK] Installed missing media list template: images/list/tmpl/default.php.');
  }

  /**
   * method to change the DATE default value for strict MySQL databases.
   *
   * @return void
   */

  private function updateDateColumns(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $alterQueries = [
      // Table #__contentbuilder_ng_forms
      "ALTER TABLE `#__contentbuilder_ng_forms` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
      "UPDATE `#__contentbuilder_ng_forms` SET `created` = NULL WHERE `created` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilder_ng_forms` MODIFY `modified` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_forms` SET `modified` = NULL WHERE `modified` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilder_ng_forms` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_forms` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilder_ng_forms` MODIFY `rand_date_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_forms` SET `rand_date_update` = NULL WHERE `rand_date_update` = '0000-00-00'",

      // Table #__contentbuilder_ng_records
      "ALTER TABLE `#__contentbuilder_ng_records` MODIFY `publish_up` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_records` SET `publish_up` = NULL WHERE `publish_up` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_records` MODIFY `publish_down` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_records` SET `publish_down` = NULL WHERE `publish_down` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_records` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_records` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_records` MODIFY `rand_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_records` SET `rand_date` = NULL WHERE `rand_date` = '0000-00-00'",

      // Table #__contentbuilder_ng_articles (si présent)
      "ALTER TABLE `#__contentbuilder_ng_articles` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_articles` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",

      // Table #__contentbuilder_ng_users (dates de vérification)
      "ALTER TABLE `#__contentbuilder_ng_users` MODIFY `verification_date_view` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_users` SET `verification_date_view` = NULL WHERE `verification_date_view` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_users` MODIFY `verification_date_new` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_users` SET `verification_date_new` = NULL WHERE `verification_date_new` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_users` MODIFY `verification_date_edit` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_users` SET `verification_date_edit` = NULL WHERE `verification_date_edit` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilder_ng_rating_cache` MODIFY COLUMN `date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_rating_cache` SET `date` = NULL WHERE `date` = '0000-00-00'",

      // Table #__contentbuilder_ng_verifications
      "ALTER TABLE `#__contentbuilder_ng_verifications` MODIFY `start_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_verifications` SET `start_date` = NULL WHERE `start_date` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilder_ng_verifications` MODIFY `verification_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilder_ng_verifications` SET `verification_date` = NULL WHERE `verification_date` = '0000-00-00'"
    ];

    foreach ($alterQueries as $query) {
      try {
        $db->setQuery($query)->execute();
      } catch (\Exception $e) {
        // Silencieux si la colonne est déjà correcte ou table inexistante
        $msg = '[WARNING] Could not alter date column: ' . $e->getMessage() . '.';
        $this->log($msg, Log::WARNING);
      }
    }

    $this->migrateStoragesAuditColumns();
    $this->migrateInternalStorageDataTablesAuditColumns();

    $msg = '[OK] Date fields updated to support NULL correctly, if necessary.';
    $this->log($msg);
  }

  private function storageAuditColumnDefinition(string $column): string
  {
    return match ($column) {
      'created' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
      'modified' => 'DATETIME NULL DEFAULT NULL',
      'created_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      'modified_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      default => 'TEXT NULL',
    };
  }

  private function getStoragesTableColumnsLower(): array
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    try {
      $columns = $db->getTableColumns('#__contentbuilder_ng_storages', false);
      return array_change_key_case($columns ?: [], CASE_LOWER);
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilder_ng_storages columns: ' . $e->getMessage(), Log::WARNING);
      return [];
    }
  }

  private function migrateStoragesAuditColumns(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $columns = $this->getStoragesTableColumnsLower();
    if (empty($columns)) {
      return;
    }

    $legacyToStandard = [
      'last_update' => 'modified',
      'last_updated' => 'modified',
      'createdby' => 'created_by',
      'modifiedby' => 'modified_by',
      'updated_by' => 'modified_by',
    ];

    foreach ($legacyToStandard as $legacy => $target) {
      if (!array_key_exists($legacy, $columns)) {
        continue;
      }

      $targetDefinition = $this->storageAuditColumnDefinition($target);

      if (!array_key_exists($target, $columns)) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_storages') .
            ' CHANGE ' . $db->quoteName($legacy) . ' ' . $db->quoteName($target) . ' ' . $targetDefinition
          )->execute();
          $this->log("[OK] Renamed storage audit column {$legacy} to {$target}.");
          $columns = $this->getStoragesTableColumnsLower();
          continue;
        } catch (\Throwable $e) {
          $this->log("[WARNING] Failed renaming storage audit column {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
        }
      }

      try {
        if ($target === 'modified' || $target === 'created') {
          $db->setQuery(
            'UPDATE ' . $db->quoteName('#__contentbuilder_ng_storages') .
            ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy) .
            ' WHERE (' . $db->quoteName($target) . ' IS NULL OR ' . $db->quoteName($target) . " IN ('0000-00-00', '0000-00-00 00:00:00'))" .
            ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL' .
            ' AND ' . $db->quoteName($legacy) . " NOT IN ('0000-00-00', '0000-00-00 00:00:00')"
          )->execute();
        } else {
          $db->setQuery(
            'UPDATE ' . $db->quoteName('#__contentbuilder_ng_storages') .
            ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy) .
            ' WHERE (' . $db->quoteName($target) . " = '' OR " . $db->quoteName($target) . ' IS NULL)' .
            ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL' .
            ' AND ' . $db->quoteName($legacy) . " <> ''"
          )->execute();
        }
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed copying data from {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
      }

      if ($legacy !== $target) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_storages') .
            ' DROP COLUMN ' . $db->quoteName($legacy)
          )->execute();
          $this->log("[OK] Removed legacy storage audit column {$legacy}.");
          $columns = $this->getStoragesTableColumnsLower();
        } catch (\Throwable $e) {
          $this->log("[WARNING] Failed removing legacy storage audit column {$legacy}: " . $e->getMessage(), Log::WARNING);
        }
      }
    }

    $required = ['created', 'modified', 'created_by', 'modified_by'];
    foreach ($required as $column) {
      if (array_key_exists($column, $columns)) {
        continue;
      }
      try {
        $db->setQuery(
          'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_storages') .
          ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $this->storageAuditColumnDefinition($column)
        )->execute();
        $this->log("[OK] Added storage audit column {$column}.");
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed adding storage audit column {$column}: " . $e->getMessage(), Log::WARNING);
      }
    }

    $normalizationQueries = [
      "ALTER TABLE `#__contentbuilder_ng_storages` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
      "ALTER TABLE `#__contentbuilder_ng_storages` MODIFY `modified` DATETIME NULL DEFAULT NULL",
      "ALTER TABLE `#__contentbuilder_ng_storages` MODIFY `created_by` VARCHAR(255) NOT NULL DEFAULT ''",
      "ALTER TABLE `#__contentbuilder_ng_storages` MODIFY `modified_by` VARCHAR(255) NOT NULL DEFAULT ''",
      "UPDATE `#__contentbuilder_ng_storages` SET `created` = NULL WHERE `created` IN ('0000-00-00', '0000-00-00 00:00:00')",
      "UPDATE `#__contentbuilder_ng_storages` SET `modified` = NULL WHERE `modified` IN ('0000-00-00', '0000-00-00 00:00:00')",
      "UPDATE `#__contentbuilder_ng_storages` SET `created_by` = '' WHERE `created_by` IS NULL",
      "UPDATE `#__contentbuilder_ng_storages` SET `modified_by` = '' WHERE `modified_by` IS NULL",
    ];

    foreach ($normalizationQueries as $query) {
      try {
        $db->setQuery($query)->execute();
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not normalize storage audit columns: ' . $e->getMessage(), Log::WARNING);
      }
    }
  }

  private function migrateInternalStorageDataTablesAuditColumns(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $now = Factory::getDate()->toSql();

    try {
      $query = $db->getQuery(true)
        ->select($db->quoteName(['id', 'name']))
        ->from($db->quoteName('#__contentbuilder_ng_storages'))
        ->where('(' . $db->quoteName('bytable') . ' = 0 OR ' . $db->quoteName('bytable') . ' IS NULL)')
        ->where($db->quoteName('name') . " <> ''");

      $db->setQuery($query);
      $storages = $db->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not load internal storages for audit migration: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if (empty($storages)) {
      return;
    }

    $processed = 0;
    $updated = 0;
    $missingTables = 0;
    $duplicatesRemoved = 0;

    foreach ($storages as $storage) {
      $processed++;

      $storageId = (int) ($storage['id'] ?? 0);
      $name = strtolower(trim((string) ($storage['name'] ?? '')));

      if ($storageId < 1 || $name === '') {
        continue;
      }

      if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        $this->log("[WARNING] Skipping internal storage {$storageId}: invalid table name {$name}.", Log::WARNING);
        continue;
      }

      $tableAlias = '#__' . $name;
      $tableQN = $db->quoteName($tableAlias);

      try {
        $columns = $db->getTableColumns($tableAlias, false);
      } catch (\Throwable $e) {
        $exceptionMessage = (string) $e->getMessage();
        $isMissingTable = stripos($exceptionMessage, "doesn't exist") !== false
          || stripos($exceptionMessage, 'does not exist') !== false;

        if ($isMissingTable) {
          $this->log(
            "[INFO] Data table {$tableAlias} (storage {$storageId}) is missing, skipping its audit migration.",
            Log::INFO
          );
        } else {
          $this->log(
            "[WARNING] Could not inspect data table {$tableAlias} (storage {$storageId}): {$exceptionMessage}. Continuing installation.",
            Log::WARNING
          );
        }

        $missingTables++;
        continue;
      }

      if (empty($columns)) {
        $missingTables++;
        continue;
      }

      $columns = array_change_key_case($columns, CASE_LOWER);
      $tableChanged = false;
      $removedForTable = $this->removeDuplicateIndexes($db, $tableQN, $tableAlias, $storageId);
      if ($removedForTable > 0) {
        $duplicatesRemoved += $removedForTable;
        $tableChanged = true;
      }
      $indexedColumns = $this->getIndexedColumns($db, $tableQN);

      $requiredColumns = [
        'id' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'storage_id' => 'INT NOT NULL DEFAULT ' . $storageId,
        'user_id' => 'INT NOT NULL DEFAULT 0',
        'created' => 'DATETIME NOT NULL DEFAULT ' . $db->quote($now),
        'created_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
        'modified_user_id' => 'INT NOT NULL DEFAULT 0',
        'modified' => 'DATETIME NULL DEFAULT NULL',
        'modified_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      ];

      foreach ($requiredColumns as $column => $definition) {
        if (array_key_exists($column, $columns)) {
          continue;
        }

        try {
          $db->setQuery(
            'ALTER TABLE ' . $tableQN
            . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
          )->execute();
          $columns[$column] = true;
          $tableChanged = true;
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed adding audit column {$column} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      if (array_key_exists('storage_id', $columns)) {
        try {
          $db->setQuery(
            'UPDATE ' . $tableQN
            . ' SET ' . $db->quoteName('storage_id') . ' = ' . $storageId
            . ' WHERE ' . $db->quoteName('storage_id') . ' IS NULL OR ' . $db->quoteName('storage_id') . ' = 0'
          )->execute();
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed normalizing storage_id on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      foreach (['created_by', 'modified_by'] as $actorColumn) {
        if (!array_key_exists($actorColumn, $columns)) {
          continue;
        }
        try {
          $db->setQuery(
            'UPDATE ' . $tableQN
            . ' SET ' . $db->quoteName($actorColumn) . " = ''"
            . ' WHERE ' . $db->quoteName($actorColumn) . ' IS NULL'
          )->execute();
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed normalizing {$actorColumn} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      foreach (['storage_id', 'user_id', 'created', 'modified_user_id', 'modified'] as $indexColumn) {
        if (!array_key_exists($indexColumn, $columns)) {
          continue;
        }
        if (isset($indexedColumns[$indexColumn])) {
          continue;
        }

        try {
          $db->setQuery(
            'ALTER TABLE ' . $tableQN
            . ' ADD INDEX (' . $db->quoteName($indexColumn) . ')'
          )->execute();
          $indexedColumns[$indexColumn] = true;
          $tableChanged = true;
        } catch (\Throwable $e) {
          $message = strtolower((string) $e->getMessage());
          if (strpos($message, 'too many keys') !== false) {
            $this->log(
              "[WARNING] Max index count reached on {$tableAlias} (storage {$storageId}); skipping remaining index additions.",
              Log::WARNING
            );
            break;
          }
          if (
            strpos($message, 'duplicate') === false
            && strpos($message, 'already exists') === false
          ) {
            $this->log(
              "[WARNING] Failed adding index {$indexColumn} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
              Log::WARNING
            );
          } else {
            $indexedColumns[$indexColumn] = true;
          }
        }
      }

      if ($tableChanged) {
        $updated++;
      }
    }

    $this->log("[OK] Internal storage audit migration complete. Processed: {$processed}, updated: {$updated}, missing tables: {$missingTables}, duplicate indexes removed: {$duplicatesRemoved}.");
  }


  /* Rename com_contentbuilder ->  com_contentbuilder_ng */
  private function migrateLegacyContentbuilderName(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $legacyElement = 'com_contentbuilder';
    $targetElement = 'com_contentbuilder_ng';

    $legacyQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($legacyQuery);
    $legacyId = (int) $db->loadResult();

    if ($legacyId === 0) {
      return;
    }

    $targetQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($targetQuery);
    if ((int) $db->loadResult() > 0) {
      $message = "[WARNING] Legacy extension {$legacyElement} detected but {$targetElement} already exists. It will be removed after update.";
      $this->log($message, Log::WARNING);
      $columns = [];
      try {
        $columns = array_keys($db->getTableColumns('#__extensions'));
      } catch (\Throwable $e) {
        $columns = ['extension_id', 'element', 'name', 'enabled', 'client_id'];
      }

      $listQuery = $db->getQuery(true)
        ->select($db->quoteName($columns))
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
        ->where($db->quoteName('element') . ' IN (' . $db->quote($legacyElement) . ', ' . $db->quote($targetElement) . ')');

      $db->setQuery($listQuery);
      $rows = $db->loadAssocList() ?: [];
      if ($rows) {
        foreach ($rows as $row) {
          ksort($row);
          $payload = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          $payload = $payload !== false ? $payload : '[unserializable row]';
          $this->log('[WARNING] Component row: ' . $payload, Log::WARNING);
        }
      } else {
        $this->log('[INFO] Component list empty for legacy/target elements.');
      }
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('extension_id') . ' = ' . $legacyId)
      )->execute();

      $this->log("[OK] Migrated extension element from {$legacyElement} to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[ERROR] Failed to migrate legacy extension element: " . $e->getMessage();
      $this->log($message, Log::ERROR);
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__assets'))
          ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('name') . ' = ' . $db->quote($legacyElement))
      )->execute();
      $this->log("[OK] Renamed asset ownership from {$legacyElement} to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not update #__assets for {$legacyElement}: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }

    $this->updateMenuLinks($legacyElement, $targetElement);

    // Menu alias/title
    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder_ng'))
          ->set($db->quoteName('path') . ' = ' . $db->quote('contentbuilder_ng'))
          ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDER_NG'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder'))
          ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder_ng%'))
      )->execute();
      $this->log("[OK] Renamed legacy menu entry to contentbuilder_ng.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not rename legacy menu entry: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }
  }

  private function updateMenuLinks(string $legacyElement, string $targetElement): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $conditions = $this->buildMenuLinkOptionWhereClauses($db, $legacyElement);

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set(
            $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', ' .
            $db->quote('option=' . $legacyElement) . ', ' . $db->quote('option=' . $targetElement) . ')'
          )
          ->where('(' . implode(' OR ', $conditions) . ')')
      )->execute();
      $this->log("[OK] Updated menu links to point to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not update menu links for legacy option: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }
  }

  private function buildMenuLinkOptionWhereClauses(DatabaseInterface $db, string $option): array
  {
    $param = 'option=' . $option;

    return [
      $db->quoteName('link') . ' = ' . $db->quote('index.php?' . $param),
      $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?' . $param . '&%'),
      $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param),
      $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param . '&%'),
    ];
  }

  private function normalizeBrokenTargetMenuLinks(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $passes = 0;
    $total = 0;

    // Earlier builds could transform com_contentbuilder_ng into com_contentbuilder_ng_ng.
    while ($passes < 5) {
      $passes++;
      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set(
              $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', ' .
              $db->quote('option=com_contentbuilder_ng_ng') . ', ' . $db->quote('option=com_contentbuilder_ng') . ')'
            )
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder_ng_ng%'))
        )->execute();

        $affected = (int) $db->getAffectedRows();
        $total += $affected;

        if ($affected === 0) {
          break;
        }
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed to normalize broken menu links: ' . $e->getMessage(), Log::WARNING);
        break;
      }
    }

    if ($total > 0) {
      $this->log("[OK] Normalized {$total} broken com_contentbuilder_ng menu link(s).");
    }
  }

  private function deduplicateTargetComponentExtensions(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetElement = 'com_contentbuilder_ng';

    try {
      $rows = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['extension_id', 'enabled', 'manifest_cache']))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('client_id') . ' = 1')
          ->order($db->quoteName('extension_id') . ' DESC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading component duplicates: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    $rows = array_values(array_filter($rows, static function (array $row): bool {
      return (int) ($row['extension_id'] ?? 0) > 0;
    }));

    if (count($rows) <= 1) {
      return;
    }

    $ids = array_values(array_unique(array_map(
      static fn(array $row): int => (int) ($row['extension_id'] ?? 0),
      $rows
    )));

    $menuRefCounts = [];
    foreach ($ids as $id) {
      $menuRefCounts[$id] = 0;
    }

    try {
      $refs = $db->setQuery(
        $db->getQuery(true)
          ->select([
            $db->quoteName('component_id'),
            'COUNT(1) AS refs',
          ])
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->where($db->quoteName('component_id') . ' IN (' . implode(',', $ids) . ')')
          ->group($db->quoteName('component_id'))
      )->loadAssocList() ?: [];

      foreach ($refs as $ref) {
        $refId = (int) ($ref['component_id'] ?? 0);
        if ($refId > 0) {
          $menuRefCounts[$refId] = (int) ($ref['refs'] ?? 0);
        }
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading menu references for duplicate components: ' . $e->getMessage(), Log::WARNING);
    }

    $keepId = 0;
    $bestEnabled = -1;
    $bestHasManifest = -1;
    $bestMenuRefs = -1;
    $bestId = -1;

    foreach ($rows as $row) {
      $id = (int) ($row['extension_id'] ?? 0);
      if ($id <= 0) {
        continue;
      }

      $enabled = (int) ($row['enabled'] ?? 0);
      $hasManifest = trim((string) ($row['manifest_cache'] ?? '')) !== '' ? 1 : 0;
      $menuRefs = (int) ($menuRefCounts[$id] ?? 0);

      $isBetter = false;
      if ($keepId === 0) {
        $isBetter = true;
      } elseif ($enabled > $bestEnabled) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest > $bestHasManifest) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest === $bestHasManifest && $menuRefs > $bestMenuRefs) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest === $bestHasManifest && $menuRefs === $bestMenuRefs && $id > $bestId) {
        $isBetter = true;
      }

      if ($isBetter) {
        $keepId = $id;
        $bestEnabled = $enabled;
        $bestHasManifest = $hasManifest;
        $bestMenuRefs = $menuRefs;
        $bestId = $id;
      }
    }

    if ($keepId <= 0) {
      return;
    }

    $removeIds = array_values(array_filter($ids, static fn(int $id): bool => $id !== $keepId));
    if (empty($removeIds)) {
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('component_id') . ' = ' . $keepId)
          ->where($db->quoteName('component_id') . ' IN (' . implode(',', $removeIds) . ')')
      )->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed remapping menu component ids during dedupe: ' . $e->getMessage(), Log::WARNING);
    }

    foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
      try {
        $db->setQuery(
          $db->getQuery(true)
            ->delete($db->quoteName($table))
            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
        )->execute();
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed cleaning ' . $table . ' during component dedupe: ' . $e->getMessage(), Log::WARNING);
      }
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->delete($db->quoteName('#__extensions'))
          ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
      )->execute();

      $deleted = (int) $db->getAffectedRows();
      $this->log(
        "[OK] Deduplicated {$targetElement} component rows: kept extension_id {$keepId}, removed {$deleted} duplicate(s)."
      );
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed deleting duplicate component rows: ' . $e->getMessage(), Log::WARNING);
    }
  }

  private function resolveMenuAlias(int $parentId, string $baseAlias): string
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $alias = $baseAlias;
    $suffix = 2;

    while ($suffix < 100) {
      $query = $db->getQuery(true)
        ->select('COUNT(1)')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
        ->where($db->quoteName('client_id') . ' = 1')
        ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));

      $db->setQuery($query);
      if ((int) $db->loadResult() === 0) {
        return $alias;
      }

      $alias = $baseAlias . '-' . $suffix;
      $suffix++;
    }

    return $baseAlias . '-' . time();
  }

  private function ensureAdministrationMainMenuEntry(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetElement = 'com_contentbuilder_ng';

    try {
      $componentId = (int) $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName('extension_id'))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('client_id') . ' = 1')
      )->loadResult();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading component extension id: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if ($componentId === 0) {
      $this->log('[WARNING] Cannot ensure admin menu entry: com_contentbuilder_ng extension id is missing.', Log::WARNING);
      return;
    }

    $mainRows = [];
    try {
      $mainRows = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['id', 'alias', 'path']))
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->where($db->quoteName('parent_id') . ' = 1')
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where(
            '(' .
            $db->quoteName('component_id') . ' = ' . $componentId .
            ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilder_ng%') .
            ')'
          )
          ->order($db->quoteName('id') . ' ASC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed checking existing admin menu entry: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if (!empty($mainRows)) {
      $mainId = (int) $mainRows[0]['id'];
      $alias = trim((string) ($mainRows[0]['alias'] ?? ''));
      $path = trim((string) ($mainRows[0]['path'] ?? ''));

      if ($alias === '') {
        $alias = $this->resolveMenuAlias(1, 'contentbuilder_ng');
      }
      if ($path === '') {
        $path = $alias;
      }

      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDER_NG'))
            ->set($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->set($db->quoteName('path') . ' = ' . $db->quote($path))
            ->set($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilder_ng'))
            ->set($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('component_id') . ' = ' . $componentId)
            ->set($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . $mainId)
        )->execute();

        $this->log('[OK] Administration component menu entry checked and updated.');
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed updating existing admin menu entry: ' . $e->getMessage(), Log::WARNING);
      }

      return;
    }

    $root = [];
    try {
      $root = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['id', 'rgt']))
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('root'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->order($db->quoteName('id') . ' ASC')
      )->loadAssoc() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed loading admin menu root: ' . $e->getMessage(), Log::WARNING);
    }

    if (empty($root)) {
      try {
        $root = $db->setQuery(
          $db->getQuery(true)
            ->select($db->quoteName(['id', 'rgt']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = 1')
        )->loadAssoc() ?: [];
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not load fallback menu root: ' . $e->getMessage(), Log::WARNING);
      }
    }

    if (empty($root)) {
      $this->log('[ERROR] Cannot recreate admin menu entry: root node not found.', Log::ERROR);
      return;
    }

    $rootId = (int) ($root['id'] ?? 1);
    $rootRgt = (int) ($root['rgt'] ?? 0);
    if ($rootRgt <= 0) {
      $this->log('[ERROR] Cannot recreate admin menu entry: invalid root rgt value.', Log::ERROR);
      return;
    }

    $alias = $this->resolveMenuAlias($rootId, 'contentbuilder_ng');

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + 2')
          ->where($db->quoteName('rgt') . ' >= ' . $rootRgt)
      )->execute();

      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + 2')
          ->where($db->quoteName('lft') . ' > ' . $rootRgt)
      )->execute();

      $columns = [
        'menutype',
        'title',
        'alias',
        'note',
        'path',
        'link',
        'type',
        'published',
        'parent_id',
        'level',
        'component_id',
        'checked_out',
        'checked_out_time',
        'browserNav',
        'access',
        'img',
        'template_style_id',
        'params',
        'lft',
        'rgt',
        'home',
        'language',
        'client_id',
      ];

      $values = [
        $db->quote('main'),
        $db->quote('COM_CONTENTBUILDER_NG'),
        $db->quote($alias),
        $db->quote(''),
        $db->quote($alias),
        $db->quote('index.php?option=com_contentbuilder_ng'),
        $db->quote('component'),
        1,
        $rootId,
        1,
        $componentId,
        0,
        'NULL',
        0,
        1,
        $db->quote('class:component'),
        0,
        $db->quote(''),
        $rootRgt,
        $rootRgt + 1,
        0,
        $db->quote('*'),
        1,
      ];

      $db->setQuery(
        $db->getQuery(true)
          ->insert($db->quoteName('#__menu'))
          ->columns($db->quoteName($columns))
          ->values(implode(', ', $values))
      )->execute();

      $this->log('[OK] Administration component menu entry recreated.');
    } catch (\Throwable $e) {
      $this->log('[ERROR] Failed recreating administration menu entry: ' . $e->getMessage(), Log::ERROR);
    }
  }

  private function ensureSubmenuQuickTasks(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $componentId = (int) $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName('extension_id'))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilder_ng'))
          ->where($db->quoteName('client_id') . ' = 1')
      )->loadResult();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading extension id for submenu quicktasks: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if ($componentId === 0) {
      return;
    }

    $targets = [
      [
        'label' => 'Storages',
        'links' => [
          'index.php?option=com_contentbuilder_ng&view=storages',
          'index.php?option=com_contentbuilder_ng&task=storages.display',
        ],
        'quicktask' => 'index.php?option=com_contentbuilder_ng&task=storage.add',
        'quicktask_title' => 'COM_CONTENTBUILDER_NG_MENUS_NEW_STORAGE',
      ],
      [
        'label' => 'Views',
        'links' => [
          'index.php?option=com_contentbuilder_ng&view=forms',
          'index.php?option=com_contentbuilder_ng&task=forms.display',
        ],
        'quicktask' => 'index.php?option=com_contentbuilder_ng&task=form.add',
        'quicktask_title' => 'COM_CONTENTBUILDER_NG_MENUS_NEW_VIEW',
      ],
    ];

    foreach ($targets as $target) {
      $quotedLinks = array_map(
        fn(string $link): string => $db->quote($link),
        $target['links']
      );

      try {
        $rows = $db->setQuery(
          $db->getQuery(true)
            ->select($db->quoteName(['id', 'params']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('component_id') . ' = ' . $componentId)
            ->where($db->quoteName('parent_id') . ' > 1')
            ->where($db->quoteName('link') . ' IN (' . implode(',', $quotedLinks) . ')')
        )->loadAssocList() ?: [];
      } catch (\Throwable $e) {
        $this->log(
          '[WARNING] Failed loading submenu entries for ' . $target['label'] . ': ' . $e->getMessage(),
          Log::WARNING
        );
        continue;
      }

      if (empty($rows)) {
        continue;
      }

      $updated = 0;
      foreach ($rows as $row) {
        $menuId = (int) ($row['id'] ?? 0);
        if ($menuId === 0) {
          continue;
        }

        $params = new Registry((string) ($row['params'] ?? ''));
        $changed = false;

        if ((string) $params->get('menu-quicktask') !== $target['quicktask']) {
          $params->set('menu-quicktask', $target['quicktask']);
          $changed = true;
        }
        if ((string) $params->get('menu-quicktask-title') !== $target['quicktask_title']) {
          $params->set('menu-quicktask-title', $target['quicktask_title']);
          $changed = true;
        }
        if ((string) $params->get('menu-quicktask-icon') !== 'plus') {
          $params->set('menu-quicktask-icon', 'plus');
          $changed = true;
        }

        if (!$changed) {
          continue;
        }

        try {
          $db->setQuery(
            $db->getQuery(true)
              ->update($db->quoteName('#__menu'))
              ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
              ->where($db->quoteName('id') . ' = ' . $menuId)
          )->execute();
          $updated++;
        } catch (\Throwable $e) {
          $this->log(
            '[WARNING] Failed updating quicktask menu params for menu #' . $menuId . ': ' . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      if ($updated > 0) {
        $this->log('[OK] Updated Joomla quicktask (+) for ' . $target['label'] . ' submenu (' . $updated . ' entry).');
      }
    }
  }

  private function renameLegacyTables(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $prefix = $db->getPrefix();
    $existing = $db->getTableList();
    $renamed = 0;
    $skipped = 0;
    $missing = 0;

    foreach (self::LEGACY_TABLE_RENAMES as $legacy => $target) {
      $legacyFull = $prefix . $legacy;
      $targetFull = $prefix . $target;

      if (!in_array($legacyFull, $existing, true)) {
        $missing++;
        continue;
      }
      if (in_array($targetFull, $existing, true)) {
        $this->log("[WARNING] Legacy table {$legacyFull} detected but {$targetFull} already exists, skipping rename.", Log::WARNING);
        $skipped++;
        continue;
      }

      try {
        $db->setQuery(
          "RENAME TABLE " . $db->quoteName($legacyFull) . " TO " . $db->quoteName($targetFull)
        )->execute();
        $this->log("[OK] Renamed table {$legacyFull} to {$targetFull}.");
        $renamed++;
        $existing[] = $targetFull;
        $existing = array_filter($existing, static fn($name) => $name !== $legacyFull);
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed to rename table {$legacyFull}: " . $e->getMessage(), Log::WARNING);
      }
    }

    $total = count(self::LEGACY_TABLE_RENAMES);
    $this->log("[OK] Table migration summary: renamed {$renamed}, skipped {$skipped}, missing {$missing} of {$total}.");
  }

  private function activatePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    // Active les plugins fournis par le package.
    $plugins = $this->getPlugins();

    foreach ($plugins as $folder => $elements) {
      foreach ($elements as $element) {
        $query = $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('enabled') . ' = 1')
          ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
          ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
          ->where($db->quoteName('element') . ' = ' . $db->quote($element));

        try {
          $db->setQuery($query)->execute();
          $this->log("[OK] Plugin enabled: {$folder}/{$element}");
        } catch (\Throwable $e) {
          $this->log("[ERROR] Failed enabling {$folder}/{$element}: " . $e->getMessage(), Log::ERROR);
        }
      }
    }
  }

  private function ensurePluginsInstalled(?string $source = null, bool $forceUpdate = false): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $installer = new Installer();
    $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));

    $plugins = $this->getPlugins();
    $refreshTotal = 0;
    $refreshIndex = 0;

    if ($forceUpdate) {
      foreach ($plugins as $elements) {
        $refreshTotal += count($elements);
      }
    }

    foreach ($plugins as $folder => $elements) {
      foreach ($elements as $element) {
        $query = $db->getQuery(true)
          ->select($db->quoteName(['extension_id', 'manifest_cache']))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
          ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
          ->where($db->quoteName('element') . ' = ' . $db->quote($element));

        $db->setQuery($query);
        $row = $db->loadAssoc() ?: [];
        $id = (int) ($row['extension_id'] ?? 0);
        $installedVersion = null;
        if (!empty($row['manifest_cache'])) {
          $cache = json_decode($row['manifest_cache'], true);
          $installedVersion = is_array($cache) ? ($cache['version'] ?? null) : null;
        }

        if ($id > 0) {
          $path = $this->resolvePluginSourcePath($source, $folder, $element);
          if (!is_dir($path)) {
            $this->log("[WARNING] Plugin folder not found: {$path}", Log::WARNING);
            continue;
          }

          if ($forceUpdate) {
            $refreshIndex++;
            $rank = $refreshTotal > 0 ? " ({$refreshIndex}/{$refreshTotal})" : '';
            $ok = $installer->install($path);
            if ($ok) {
              $this->log("[OK] Plugin refreshed{$rank}: {$folder}/{$element}");
            } else {
              $this->log("[ERROR] Plugin refresh failed{$rank}: {$folder}/{$element}", Log::ERROR);
            }
            continue;
          }

          $manifestVersion = $this->getPluginManifestVersion($path);
          $needsUpdate = false;

          if (!$installedVersion || !$manifestVersion) {
            $needsUpdate = true;
          } elseif (version_compare($installedVersion, $manifestVersion, '<')) {
            $needsUpdate = true;
          } elseif ($installedVersion !== $manifestVersion) {
            // Same or higher but different string, refresh to keep manifest_cache aligned.
            $needsUpdate = true;
          }

          if (!$needsUpdate) {
            $this->log("[INFO] Plugin already installed: {$folder}/{$element} (version {$installedVersion})");
            continue;
          }

          $ok = $installer->install($path);
          if ($ok) {
            $this->log("[OK] Plugin updated: {$folder}/{$element} (version {$installedVersion} -> {$manifestVersion})");
          } else {
            $this->log("[ERROR] Plugin update failed: {$folder}/{$element}", Log::ERROR);
          }
          continue;
        }

        $path = $this->resolvePluginSourcePath($source, $folder, $element);
        if (!is_dir($path)) {
          $this->log("[WARNING] Plugin folder not found: {$path}", Log::WARNING);
          continue;
        }

        $ok = $installer->install($path);
        if ($ok) {
          $this->log("[OK] Plugin installed: {$folder}/{$element}");
        } else {
          $this->log("[ERROR] Plugin install failed: {$folder}/{$element}", Log::ERROR);
        }
      }
    }
  }

  private function resolvePluginSourcePath(?string $source, string $folder, string $element): string
  {
    $source = $source ? rtrim($source, '/') : null;
    $candidates = [];

    if ($source) {
      $candidates[] = $source . '/plugins/' . $folder . '/' . $element;
      $legacy = $this->getLegacyPluginSourcePath($source, $folder, $element);
      if ($legacy) {
        $candidates[] = $legacy;
      }
    }

    $candidates[] = JPATH_ROOT . '/plugins/' . $folder . '/' . $element;

    foreach ($candidates as $candidate) {
      if ($candidate && is_dir($candidate)) {
        return $candidate;
      }
    }

    return $candidates[0] ?? (JPATH_ROOT . '/plugins/' . $folder . '/' . $element);
  }

  private function getPluginManifestVersion(string $path): ?string
  {
    $files = glob(rtrim($path, '/') . '/*.xml') ?: [];
    foreach ($files as $file) {
      try {
        $xml = simplexml_load_file($file);
        if (!$xml || $xml->getName() !== 'extension') {
          continue;
        }
        $version = isset($xml->version) ? trim((string) $xml->version) : '';
        if ($version !== '') {
          return $version;
        }
        $attrVersion = isset($xml['version']) ? trim((string) $xml['version']) : '';
        if ($attrVersion !== '') {
          return $attrVersion;
        }
      } catch (\Throwable $e) {
        continue;
      }
    }
    return null;
  }

  private function getLegacyPluginSourcePath(string $source, string $folder, string $element): ?string
  {
    if ($folder === 'system' && $element === 'contentbuilder_ng_system') {
      return $source . '/plugins/plg_system';
    }

    if ($folder === 'contentbuilder_ng_themes') {
      $candidates = [
        $source . '/plugins/contentbuilder_themes_ng/' . $element,
        $source . '/plugins/contentbuilder_themes/' . $element,
      ];

      foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
          return $candidate;
        }
      }

      return $candidates[0];
    }

    if ($folder === 'content' && str_starts_with($element, 'contentbuilder_ng_')) {
      $suffix = substr($element, strlen('contentbuilder_ng_'));
      return $source . '/plugins/plg_content_' . $suffix;
    }

    if (str_starts_with($folder, 'contentbuilder_ng_')) {
      $short = substr($folder, strlen('contentbuilder_ng_'));
      return $source . '/plugins/plg_' . $short . '_' . $element;
    }

    return null;
  }

  private function resolveInstallSourcePath($parent): ?string
  {
    $candidates = [];
    $pushCandidate = static function (array &$list, $path): void {
      $path = trim((string) ($path ?? ''));
      if ($path === '') {
        return;
      }

      if (is_file($path)) {
        $path = dirname($path);
      }

      $path = rtrim($path, '/\\');
      if ($path === '') {
        return;
      }

      if (!in_array($path, $list, true)) {
        $list[] = $path;
      }
    };

    if (is_object($parent)) {
      if (method_exists($parent, 'getPath')) {
        $pushCandidate($candidates, $parent->getPath('source'));
        $pushCandidate($candidates, $parent->getPath('manifest'));
      }

      if (method_exists($parent, 'getParent')) {
        $parentInstaller = $parent->getParent();
        if (is_object($parentInstaller) && method_exists($parentInstaller, 'getPath')) {
          $pushCandidate($candidates, $parentInstaller->getPath('source'));
          $pushCandidate($candidates, $parentInstaller->getPath('manifest'));
        }
      }
    }

    $pushCandidate($candidates, __DIR__);

    foreach ($candidates as $candidate) {
      if (is_dir($candidate . '/plugins')) {
        return $candidate;
      }
    }

    foreach ($candidates as $candidate) {
      if (is_dir($candidate) && is_dir($candidate . '/administrator') && is_dir($candidate . '/site')) {
        return $candidate;
      }
    }

    return null;
  }

  private function removeLegacyComponent(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $legacyElement = 'com_contentbuilder';
    $targetElement = 'com_contentbuilder_ng';

    $legacyQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($legacyQuery);
    $legacyId = (int) $db->loadResult();
    if ($legacyId === 0) {
      $this->log('[INFO] Legacy component not found; nothing to remove.');
      return;
    }

    $targetQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($targetQuery);
    $targetId = (int) $db->loadResult();
    if ($targetId === 0) {
      $this->log('[WARNING] Legacy component found but target component is missing; skipping removal.', Log::WARNING);
      return;
    }

    $this->log("[INFO] Legacy component {$legacyElement} detected (id {$legacyId}). Disabling it (safe mode, no uninstall).");

    // Keep admin links pointing to NG even when legacy component stays installed but disabled.
    $this->updateMenuLinks($legacyElement, $targetElement);
    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder_ng'))
          ->set($db->quoteName('path') . ' = ' . $db->quote('contentbuilder_ng'))
          ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDER_NG'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder'))
          ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder_ng%'))
      )->execute();
      $this->log('[OK] Renamed legacy menu entry to contentbuilder_ng.');
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not rename legacy menu entry: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('enabled') . ' = 0')
          ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
      )->execute();
      $this->log("[OK] Legacy component disabled: {$legacyElement} (id {$legacyId}).");
    } catch (\Throwable $e) {
      $this->log("[WARNING] Failed to disable legacy component {$legacyElement}: " . $e->getMessage(), Log::WARNING);
    }

    $this->log("[INFO] Legacy component uninstall intentionally skipped to avoid destructive uninstall SQL/hooks.");
  }

  private function getLegacyContentbuilderPlugins(): array
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $likeLegacy = $db->quote('contentbuilder%');
    $likeNg = $db->quote('contentbuilder_ng%');
    $folderCond = $db->quoteName('folder') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('folder') . ' NOT LIKE ' . $likeNg;
    $elementCond = $db->quoteName('element') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('element') . ' NOT LIKE ' . $likeNg;

    $query = $db->getQuery(true)
      ->select($db->quoteName(['extension_id', 'folder', 'element', 'enabled']))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
      ->where("(($folderCond) OR ($elementCond))");

    try {
      $db->setQuery($query);
      return $db->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to detect legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
      return [];
    }
  }

  private function disableLegacyContentbuilderPlugins(string $context = 'update'): int
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $rows = $this->getLegacyContentbuilderPlugins();

    if (empty($rows)) {
      $this->log("[INFO] No legacy ContentBuilder plugins found during {$context}.");
      return 0;
    }

    $ids = [];
    $alreadyDisabled = 0;
    foreach ($rows as $row) {
      $id = (int) ($row['extension_id'] ?? 0);
      if ($id > 0) {
        $ids[] = $id;
      }
      if ((int) ($row['enabled'] ?? 0) === 0) {
        $alreadyDisabled++;
      }
    }
    $ids = array_values(array_unique($ids));

    if (empty($ids)) {
      return 0;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('enabled') . ' = 0')
          ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $ids) . ')')
      )->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed disabling legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
      return 0;
    }

    $disabledNow = max(0, count($ids) - $alreadyDisabled);
    $this->log("[OK] Legacy ContentBuilder plugins disabled ({$disabledNow} newly disabled, " . count($ids) . " total) during {$context}.");

    return count($ids);
  }

  private function removeLegacyPlugins(): void
  {
    $disabled = $this->disableLegacyContentbuilderPlugins('postflight');
    if ($disabled > 0) {
      $this->log('[INFO] Legacy plugins are disabled (not uninstalled) to avoid destructive uninstall hooks.');
    }
  }

  private function removeLegacyPluginFolders(): void
  {
    $pluginRoot = JPATH_ROOT . '/plugins';
    if (!Folder::exists($pluginRoot)) {
      $this->log('[INFO] Plugin root folder not found; skipping legacy plugin folder cleanup.');
      return;
    }

    $paths = [];

    // 1) Known legacy plugin folders derived from current NG plugin map.
    foreach ($this->getPlugins() as $folder => $elements) {
      foreach ($elements as $element) {
        [$legacyFolder, $legacyElement] = $this->mapToLegacyPlugin($folder, $element);
        if (!$legacyFolder || !$legacyElement) {
          continue;
        }
        $paths[] = $pluginRoot . '/' . $legacyFolder . '/' . $legacyElement;
      }
    }

    // 2) Catch-all legacy folders (group or element names starting with contentbuilder but not contentbuilder_ng).
    $groupFolders = Folder::folders($pluginRoot, '.', false, true) ?: [];
    foreach ($groupFolders as $groupPath) {
      $groupName = basename($groupPath);
      $groupLower = strtolower($groupName);

      if (str_starts_with($groupLower, 'contentbuilder') && !str_starts_with($groupLower, 'contentbuilder_ng')) {
        $paths[] = $groupPath;
        continue;
      }

      if (!in_array($groupLower, ['content', 'system'], true)) {
        continue;
      }

      $elements = Folder::folders($groupPath, '.', false, true) ?: [];
      foreach ($elements as $elementPath) {
        $elementLower = strtolower(basename($elementPath));
        if (str_starts_with($elementLower, 'contentbuilder') && !str_starts_with($elementLower, 'contentbuilder_ng')) {
          $paths[] = $elementPath;
        }
      }
    }

    $paths = array_values(array_unique(array_map(static fn($path) => rtrim((string) $path, '/\\'), $paths)));
    if (empty($paths)) {
      $this->log('[INFO] No legacy plugin folders detected on filesystem.');
      return;
    }

    // Delete deeper paths first.
    usort($paths, static fn($a, $b) => strlen($b) <=> strlen($a));

    foreach ($paths as $path) {
      if (!Folder::exists($path)) {
        continue;
      }

      if (Folder::delete($path)) {
        $this->log("[OK] Legacy plugin folder deleted: {$path}");
      } else {
        $this->log("[WARNING] Failed deleting legacy plugin folder: {$path}", Log::WARNING);
      }
    }
  }

  private function mapToLegacyPlugin(string $folder, string $element): array
  {
    if ($folder === 'system' && $element === 'contentbuilder_ng_system') {
      return ['system', 'contentbuilder_system'];
    }

    if ($folder === 'content' && str_starts_with($element, 'contentbuilder_ng_')) {
      $suffix = substr($element, strlen('contentbuilder_ng_'));
      return ['content', 'contentbuilder_' . $suffix];
    }

    if (str_starts_with($folder, 'contentbuilder_ng_')) {
      $legacyFolder = 'contentbuilder_' . substr($folder, strlen('contentbuilder_ng_'));
      return [$legacyFolder, $element];
    }

    return [null, null];
  }

  private function uninstallPluginById(Installer $installer, int $id, string $folder, string $element): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    try {
      $ok = (bool) $installer->uninstall('plugin', $id, 1);
      if ($ok) {
        $this->log("[OK] Legacy plugin uninstalled: {$folder}/{$element} (id {$id}).");
        return;
      }
      $this->log("[WARNING] Legacy plugin uninstall failed: {$folder}/{$element} (id {$id}). Forcing DB cleanup.", Log::WARNING);
    } catch (\Throwable $e) {
      $this->log("[WARNING] Legacy plugin uninstall error for {$folder}/{$element} (id {$id}): " . $e->getMessage(), Log::WARNING);
    }

    try {
      $query = $db->getQuery(true)
        ->delete($db->quoteName('#__extensions'))
        ->where($db->quoteName('extension_id') . ' = ' . (int) $id);
      $db->setQuery($query)->execute();
      $this->log("[OK] Legacy plugin DB entry removed: {$folder}/{$element} (id {$id}).");
    } catch (\Throwable $e) {
      $this->log("[ERROR] Failed to remove legacy plugin DB entry {$folder}/{$element} (id {$id}): " . $e->getMessage(), Log::ERROR);
    }
  }

  private function removeDeprecatedThemePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $installer = new Installer();
    $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));

    $supportedThemes = [
      'blank',
      'joomla6',
      'dark',
      'khepri',
    ];

    $query = $db->getQuery(true)
      ->select([
        $db->quoteName('extension_id'),
        $db->quoteName('element'),
      ])
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
      ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilder_ng_themes'));
    $db->setQuery($query);
    $installedThemes = $db->loadAssocList();

    foreach ($installedThemes as $plugin) {
      $id = (int) ($plugin['extension_id'] ?? 0);
      $element = (string) ($plugin['element'] ?? '');

      if ($id === 0 || $element === '' || in_array($element, $supportedThemes, true)) {
        continue;
      }

      $this->log("[INFO] Removing unsupported theme plugin contentbuilder_ng_themes/{$element} (id {$id}).");
      $this->uninstallPluginById($installer, $id, 'contentbuilder_ng_themes', $element);
    }
  }

  private function normalizeFormThemePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $columns = $db->getTableColumns('#__contentbuilder_ng_forms', false);
      if (!is_array($columns) || !array_key_exists('theme_plugin', $columns)) {
        $this->log('[INFO] Theme normalization skipped: #__contentbuilder_ng_forms.theme_plugin column not found.');
        return;
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilder_ng_forms columns: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    $supportedThemes = $this->getPlugins()['contentbuilder_ng_themes'] ?? ['joomla6'];
    if (!in_array('joomla6', $supportedThemes, true)) {
      $supportedThemes[] = 'joomla6';
    }

    $migratedLegacy = 0;
    $migratedUnsupported = 0;

    try {
      $query = $db->getQuery(true)
        ->update($db->quoteName('#__contentbuilder_ng_forms'))
        ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
        ->where($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla3'));
      $db->setQuery($query)->execute();
      $migratedLegacy = (int) $db->getAffectedRows();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed migrating joomla3 theme references: ' . $e->getMessage(), Log::WARNING);
    }

    try {
      $query = $db->getQuery(true)
        ->select('DISTINCT ' . $db->quoteName('theme_plugin'))
        ->from($db->quoteName('#__contentbuilder_ng_forms'))
        ->where($db->quoteName('theme_plugin') . ' IS NOT NULL')
        ->where($db->quoteName('theme_plugin') . " <> ''");
      $db->setQuery($query);
      $storedThemes = $db->loadColumn() ?: [];
      $unsupportedThemes = array_values(array_diff($storedThemes, $supportedThemes));

      if (!empty($unsupportedThemes)) {
        $quotedThemes = array_map(static fn($theme) => $db->quote((string) $theme), $unsupportedThemes);

        $query = $db->getQuery(true)
          ->update($db->quoteName('#__contentbuilder_ng_forms'))
          ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
          ->where($db->quoteName('theme_plugin') . ' IN (' . implode(',', $quotedThemes) . ')');
        $db->setQuery($query)->execute();
        $migratedUnsupported = (int) $db->getAffectedRows();
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed normalizing unsupported theme references: ' . $e->getMessage(), Log::WARNING);
    }

    if ($migratedLegacy > 0 || $migratedUnsupported > 0) {
      $this->log("[OK] Normalized form theme references to joomla6: {$migratedLegacy} legacy + {$migratedUnsupported} unsupported.");
    } else {
      $this->log('[INFO] No form theme references needed normalization.');
    }
  }

  private function normalizeLegacyComponentTypes(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetType = 'com_contentbuilder_ng';
    $legacyTypes = [
      'com_contentbuilder',
      'com_contentbuilderng',
    ];
    $quotedLegacyTypes = array_map(static fn($legacyType) => $db->quote($legacyType), $legacyTypes);

    $tables = [
      '#__contentbuilder_ng_forms',
      '#__contentbuilder_ng_records',
      '#__contentbuilder_ng_articles',
      '#__contentbuilder_ng_resource_access',
    ];

    $totalUpdated = 0;
    $normalizedTables = 0;

    foreach ($tables as $table) {
      try {
        $columns = $db->getTableColumns($table, false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (!array_key_exists('type', $columns)) {
          $this->log("[INFO] Type normalization skipped: {$table}.type column not found.");
          continue;
        }
      } catch (\Throwable $e) {
        $this->log("[WARNING] Could not inspect {$table} columns for type normalization: " . $e->getMessage(), Log::WARNING);
        continue;
      }

      try {
        $query = $db->getQuery(true)
          ->update($db->quoteName($table))
          ->set($db->quoteName('type') . ' = ' . $db->quote($targetType))
          ->where($db->quoteName('type') . ' IN (' . implode(',', $quotedLegacyTypes) . ')');
        $db->setQuery($query)->execute();
        $updated = (int) $db->getAffectedRows();

        if ($updated > 0) {
          $this->log("[OK] Normalized {$updated} legacy type row(s) in {$table}.");
          $totalUpdated += $updated;
        }

        $normalizedTables++;
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed normalizing legacy types in {$table}: " . $e->getMessage(), Log::WARNING);
      }
    }

    if ($normalizedTables === 0) {
      $this->log('[INFO] Legacy type normalization skipped: no compatible tables found.');
      return;
    }

    if ($totalUpdated === 0) {
      $this->log('[INFO] No legacy com_contentbuilder type value needed normalization.');
      return;
    }

    $this->log("[OK] Legacy type normalization completed: {$totalUpdated} row(s) updated.");
  }

  private function ensureFormsNewButtonColumn(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $columns = $db->getTableColumns('#__contentbuilder_ng_forms', false);
      if (!is_array($columns)) {
        return;
      }
      if (array_key_exists('new_button', $columns)) {
        return;
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilder_ng_forms columns for new_button: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    try {
      $db->setQuery(
        'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_forms')
        . ' ADD COLUMN ' . $db->quoteName('new_button')
        . " TINYINT(1) NOT NULL DEFAULT '0'"
      )->execute();
      $this->log('[OK] Added #__contentbuilder_ng_forms.new_button column.');
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed adding #__contentbuilder_ng_forms.new_button column: ' . $e->getMessage(), Log::WARNING);
    }
  }

  private function ensureElementsLinkableDefault(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $columns = $db->getTableColumns('#__contentbuilder_ng_elements', false);
      if (!is_array($columns) || !array_key_exists('linkable', $columns)) {
        return;
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilder_ng_elements.linkable column: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    try {
      $db->setQuery(
        'ALTER TABLE ' . $db->quoteName('#__contentbuilder_ng_elements')
        . ' MODIFY ' . $db->quoteName('linkable')
        . " TINYINT(1) NOT NULL DEFAULT '0'"
      )->execute();
      $this->log('[OK] Ensured #__contentbuilder_ng_elements.linkable default is 0.');
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to set #__contentbuilder_ng_elements.linkable default to 0: ' . $e->getMessage(), Log::WARNING);
    }
  }

  /**
   * Method to run after an install/update/uninstall method
   *
   * @return void
   */
  function postflight($type, $parent)
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    // === LOG POUR DÉBOGAGE ===
    $this->log('Postflight installation method call, parameter : ' . $type . '.');

    /*
             $db->setQuery("Select id From `#__menu` Where `alias` = 'root'");
             if(!$db->loadResult()){
                 $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ), 0, '*', 0)");
                 $db->execute();
             }*/

    try {
      $db->setQuery("Update #__menu Set `title` = 'COM_CONTENTBUILDER_NG' Where `alias`='contentbuilder_ng'");
      $db->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to normalize admin menu title: ' . $e->getMessage(), Log::WARNING);
    }

    $this->removeOldLibraries();
    $this->removeObsoleteFiles();
    $this->ensureMediaListTemplateInstalled();
    $this->ensureUploadDirectoryExists();
    $this->updateDateColumns();
    $this->ensureFormsNewButtonColumn();
    $this->ensureElementsLinkableDefault();
    $this->updateMenuLinks('com_contentbuilder', 'com_contentbuilder_ng');

    $source = $this->resolveInstallSourcePath($parent);
    if ($source && is_dir($source)) {
      $this->log('[INFO] Plugin install source resolved: ' . $source, Log::INFO, false);
    } else {
      $this->log('[WARNING] Plugin install source not resolved; missing plugins may not be installable in this run.', Log::WARNING);
    }
    $this->ensurePluginsInstalled($source, $type === 'update');
    $this->activatePlugins();

    if ($type === 'update') {
      $this->removeDeprecatedThemePlugins();
      $this->normalizeFormThemePlugins();
      $this->normalizeLegacyComponentTypes();
      $this->removeLegacyComponent();
      $this->removeLegacyPlugins();
    }

    $this->deduplicateTargetComponentExtensions();
    $this->normalizeBrokenTargetMenuLinks();
    $this->ensureAdministrationMainMenuEntry();
    $this->ensureSubmenuQuickTasks();

    // On ne fait ça que sur update (et éventuellement discover_install si tu veux)
    if ($type !== 'update') {
      return;
    }

    $table = $db->quoteName('#__contentbuilder_ng_storages');

    // Vérifie l’existence de la table
    try {
      $tables = $db->getTableList();
      $expected = $db->getPrefix() . 'contentbuilder_ng_storages';

      if (!in_array($expected, $tables, true)) {
        return; // table pas présente => rien à faire
      }
    } catch (\Throwable $e) {
      // Si getTableList foire sur un driver, on tente quand même.
    }

    // Y a-t-il des ordering à 0 ?
    $db->setQuery("SELECT COUNT(*) FROM $table WHERE ordering = 0");
    $needFix = (int) $db->loadResult();

    if ($needFix === 0) {
      return;
    }

    // Max ordering existant (si tout est à 0, max = 0)
    $db->setQuery("SELECT COALESCE(MAX(ordering), 0) FROM $table");
    $max = (int) $db->loadResult();

    // IDs à réparer (ordering = 0)
    $db->setQuery("SELECT id FROM $table WHERE ordering = 0 ORDER BY id");
    $ids = $db->loadColumn() ?: [];

    // Mise à jour séquentielle
    $order = $max;
    foreach ($ids as $id) {
      $order++;

      $db->setQuery(
        "UPDATE $table SET ordering = " . (int) $order . " WHERE id = " . (int) $id
      );
      $db->execute();
    }
    // try to restore the main menu items if they got lost
    /*
    $db->setQuery("Select component_id From #__menu Where `link`='index.php?option=com_contentbuilder_ng' And parent_id = 1");
    $result = $db->loadResult();

    if(!$result) {
        
        $db->setQuery("Select extension_id From #__extensions Where `type` = 'component' And `element` = 'com_contentbuilder_ng'");
        $comp_id = $db->loadResult();
        
        if($comp_id){
            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES ('main', 'com_contentbuilder_ng', 'contentbuilder_ng', '', 'contentbuilder_ng', 'index.php?option=com_contentbuilder_ng', 'component', 0, 1, 1, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();
            $parent_id = $db->insertid();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES ('main', 'COM_CONTENTBUILDER_NG_STORAGES', 'comcontentbuilderstorages', '', 'contentbuilder_ng/comcontentbuilderstorages', 'index.php?option=com_contentbuilder&task=storages.display', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'COM_CONTENTBUILDER_NG_LIST', 'comcontentbuilderlist', '', 'contentbuilder_ng/comcontentbuilderlist', 'index.php?option=com_contentbuilder&task=forms.display', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'Try BreezingForms!', 'try-breezingforms', '', 'contentbuilder_ng/try-breezingforms', 'index.php?option=com_contentbuilder&view=contentbuilder&market=true', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'class:component', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'COM_CONTENTBUILDER_NG_ABOUT', 'comcontentbuilderabout', '', 'contentbuilder_ng/comcontentbuilderabout', 'index.php?option=com_contentbuilder&view=contentbuilder_ng', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'class:component', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = ".$rgt." Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        }
    }*/

    $this->log('[OK] Contentbuilder installation finished.');
  }
}
