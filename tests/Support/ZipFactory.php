<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Support;

use ZipArchive;

/**
 * Builds ZIP archives on the fly, including malicious ones, so no hostile
 * binary fixture has to be committed.
 */
final class ZipFactory
{
    public const string CONTENT_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE ode SYSTEM "content.dtd">
<ode xmlns="http://www.intef.es/xsd/ode" version="2.0">
<odeResources>
  <odeResource><key>exe_version</key><value>4.0</value></odeResource>
</odeResources>
<odeProperties>
  <odeProperty><key>pp_title</key><value>Test package</value></odeProperty>
  <odeProperty><key>pp_description</key><value>A description</value></odeProperty>
  <odeProperty><key>pp_lang</key><value>en</value></odeProperty>
  <odeProperty><key>pp_license</key><value>CC BY-SA 4.0</value></odeProperty>
</odeProperties>
</ode>
XML;

    /**
     * @var list<string>
     */
    private static array $created = [];

    /**
     * A valid minimal package plus $extra entries (name => contents).
     *
     * @param array<string, string> $extra
     */
    public static function valid(array $extra = []): string
    {
        return self::make(['content.xml' => self::CONTENT_XML, 'index.html' => '<!DOCTYPE html><title>t</title>'] + $extra);
    }

    /**
     * @param array<string, string> $entries  name => contents
     * @param list<string>          $symlinks entry names to mark as UNIX symlinks
     */
    public static function make(array $entries, array $symlinks = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exe-test-').'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        foreach ($symlinks as $name) {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        }
        $zip->close();
        self::$created[] = $path;

        return $path;
    }

    public static function raw(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exe-test-');
        file_put_contents($path, $bytes);
        self::$created[] = $path;

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $path) {
            @unlink($path);
        }
        self::$created = [];
    }
}
