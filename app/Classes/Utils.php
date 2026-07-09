<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined("ABSPATH")) die;

class Utils
{
    // TODO: сделать гит константы приватными через .env
    private const GIT_REPO = '';
    private const GIT_TOKEN = '';

    public static function autoupdate(): void
    {
        if(!is_admin()) return;

        $update_checker = PucFactory::buildUpdateChecker(self::GIT_REPO, UNITSHOT_PATH . '/style.css', 'unitshot-theme');
        $update_checker->getVcsApi()->enableReleaseAssets('/^unitshot-theme\.zip$/');
        $update_checker->setAuthentication(self::GIT_TOKEN);
    }

    /**
     * Транслитерация кириллицы (укр./рус.) в латиницу.
     *
     * Регистр не важен - слаг всё равно приводится к нижнему позже.
     *
     * @param string $title
     * @return string
     */
    public static function transliterate(string $title): string
    {
        if ($title === '') return $title;

        $map = [
            'а' => 'a',  'б' => 'b', 'в' => 'v',  'г' => 'h',  'ґ' => 'g',  'д' => 'd',
            'е' => 'e',  'є' => 'ie','ё' => 'e',  'ж' => 'zh', 'з' => 'z',  'и' => 'y',
            'і' => 'i',  'ї' => 'i', 'й' => 'i',  'к' => 'k',  'л' => 'l',  'м' => 'm',
            'н' => 'n',  'о' => 'o', 'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
            'у' => 'u',  'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
            'щ' => 'shch','ъ' => '', 'ы' => 'y',  'ь' => '',   'э' => 'e',  'ю' => 'iu',
            'я' => 'ia',
        ];

        foreach ($map as $cyr => $lat) {
            $upper = mb_strtoupper($cyr, 'UTF-8');
            if ($upper !== $cyr) $map[$upper] = $lat;
        }

        return strtr($title, $map);
    }
}
