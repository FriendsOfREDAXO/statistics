<?php

namespace AndiLeni\Statistics;

class NoisePatterns
{
    /**
     * @return array<int, string>
     */
    public static function getMergedLikePatterns(\rex_addon_interface $addon): array
    {
        $patterns = self::getBaseLikePatterns();
        $patterns = self::addConfigPatterns($patterns, (string) $addon->getConfig('statistics_ignored_paths', ''), 'contains');
        $patterns = self::addConfigPatterns($patterns, (string) $addon->getConfig('statistics_ignored_path_contains', ''), 'contains');
        $patterns = self::addConfigPatterns($patterns, (string) $addon->getConfig('statistics_ignored_path_ends', ''), 'ends');

        return array_values(array_unique($patterns));
    }

    /**
     * @return array<int, string>
     */
    public static function getBaseLikePatterns(): array
    {
        return [
            '%/wp-login.php%',
            '%/wp-json%',
            '%/wp-config%',
            '%/wp-admin%',
            '%/wp-includes/%',
            '%/wp-content/%',
            '%/xmlrpc.php%',
            '%/wlwmanifest.xml%',
            '%/drupal%',
            '%/joomla%',
            '%/magento%',
            '%/prestashop%',
            '%/typo3%',
            '%/shopware%',
            '%/administrator%',
            '%/admin/login%',
            '%/admin/%',
            '%/api/%',
            '%/api',
            '%/adminer%',
            '%/adminer.php%',
            '%/phpmyadmin%',
            '%/phpmyadmin2%',
            '%/pma%',
            '%/dbadmin%',
            '%/myadmin%',
            '%/webadmin%',
            '%/mysql%',
            '%/phpinfo.php%',
            '%/server-status%',
            '%/server-info%',
            '%/cgi-bin/%',
            '%/webmail%',
            '%/roundcube%',
            '%/.git/%',
            '%/vendor/phpunit%',
            '%apple-touch%',
            '%/.well-known/security.txt%',
            '%/.env%',
            '%/.htaccess%',
            '%.php%',
            '%.json%',
            '%.xml%',
            '%.yml%',
            '%.save%',
            '%.ini%',
            '%.log%',
            '%.bak%',
            '%.old%',
            '%.sql%',
        ];
    }

    /**
     * @param array<int, string> $patterns
     *
     * @return array<int, string>
     */
    private static function addConfigPatterns(array $patterns, string $configValue, string $mode): array
    {
        $lines = explode("\n", str_replace("\r", '', $configValue));
        foreach ($lines as $line) {
            $rule = strtolower(trim((string) $line));
            if ('' === $rule) {
                continue;
            }

            if ('ends' === $mode) {
                $patterns[] = '%' . $rule;
            } else {
                $patterns[] = '%' . $rule . '%';
            }
        }

        return $patterns;
    }
}
