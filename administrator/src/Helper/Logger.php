<?php

/**
 * ContentBuilder NG Logs.
 *
 * Log file manager.
 *
 * @package     ContentBuilder NG
 * @subpackage  Site.Helper
 * @since       6.0.0
 * @author      Xavier DANO
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

final class Logger
{
    private static bool $registered = false;

    private static function register(): void
    {
        if (self::$registered) {
            return;
        }

        Log::addLogger(
            [
                'text_file'         => 'com_contentbuilderng.admin.log',
                'text_entry_format' => "{DATE} {TIME} {PRIORITY}\t{CATEGORY}\t{MESSAGE}", // pas d'IP.
            ],
            Log::ALL,
            ['cb.admin']
        );

        Log::addLogger(
            [
                'text_file'         => 'com_contentbuilderng.site.log',
                'text_entry_format' => "{DATE} {TIME} {PRIORITY}\t{CATEGORY}\t{MESSAGE}",
            ],
            Log::ALL,
            ['cb.site']
        );

        self::$registered = true;
    }

    private static function category(): string
    {
        $app = Factory::getApplication();

        return $app->isClient('administrator') ? 'cb.admin' : 'cb.site';
    }

    private static function callerAt(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        for ($i = 0; $i < count($trace); $i++) {
            $frame = $trace[$i];

            // On trouve la frame Logger::<level>()
            if (($frame['class'] ?? null) !== self::class) {
                continue;
            }

            $fn = $frame['function'] ?? '';
            if (!in_array($fn, ['debug', 'info', 'warning', 'error', 'exception'], true)) {
                continue;
            }

            // file/line du point d'appel (là où Logger::info(...) est écrit)
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            // La frame suivante est l'appelant réel (StorageModel::store)
            $caller = $trace[$i + 1] ?? [];

            $callerClass = $caller['class'] ?? null;
            $callerFunc  = $caller['function'] ?? null;

            $shortClass = $callerClass
                ? substr($callerClass, strrpos($callerClass, '\\') + 1)
                : null;

            $fileBase = $file ? pathinfo($file, PATHINFO_FILENAME) : null;

            // Exemple voulu: StorageModel::store (StorageModel:411)
            $where = $shortClass ?? $fileBase ?? 'unknown';
            $what  = $callerFunc ? ($where . '::' . $callerFunc) : $where;

            if ($fileBase && $line) {
                return $what . ' (' . $fileBase . ':' . (int) $line . ')';
            }

            return $what;
        }

        return null;
    }



    /**
     * Contexte JSON = uniquement ce que tu veux “métier”
     * (pas at, pas priorité, pas date, etc.)
     */
    private static function baseContext(): array
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        return [
            'client' => $app->isClient('administrator') ? 'admin' : 'site',
            'view'   => $input->getCmd('view', ''),
            'task'   => $input->getCmd('task', ''),
            'userId' => (int) Factory::getApplication()->getIdentity()->id
        ];
    }

    private static function format(string $message, array $context = []): string
    {
        $at = self::callerAt();
        if ($at) {
            $message = "$at\t$message";
        }

        $merged = self::baseContext();

        foreach ($context as $k => $v) {
            $merged[$k] = $v;
        }

        // Si tu veux : ne pas afficher de JSON si le contexte est vide
        if (!$merged) {
            return $message;
        }

        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json ? ($message . ' | ' . $json) : $message;
    }

    /** Debug seulement si debug Joomla activé */
    public static function debug(string $message, array $context = []): void
    {
        if (!Factory::getApplication()->get('debug')) {
            return;
        }

        self::register();
        Log::add(self::format($message, $context), Log::DEBUG, self::category());
    }

    public static function info(string $message, array $context = []): void
    {
        self::register();
        Log::add(self::format($message, $context), Log::INFO, self::category());
    }

    public static function warning(string $message, array $context = []): void
    {
        self::register();
        Log::add(self::format($message, $context), Log::WARNING, self::category());
    }

    public static function error(string $message, array $context = []): void
    {
        self::register();
        Log::add(self::format($message, $context), Log::ERROR, self::category());
    }

    public static function exception(\Throwable $e, array $context = []): void
    {
        $context += [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ];

        self::error('Exception', $context);
    }
}
