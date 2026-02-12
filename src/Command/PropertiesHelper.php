<?php

declare(strict_types=1);

/**
 * This file is part of the AbraFlexi CLI package.
 *
 * (c) Vítězslav Dvořák <https://vitexsoftware.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VitexSoftware\AbraflexiCli\Command;

use AbraFlexi\RO;

/**
 * Helper class for loading and parsing AbraFlexi evidence properties.
 *
 * Uses AbraFlexi\RO::getColumnsInfo() to obtain evidence structure.
 */
class PropertiesHelper
{
    /**
     * Cached properties data.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $cache = [];

    /**
     * Cached RO instance for column info retrieval.
     */
    private static ?RO $roInstance = null;

    /**
     * Load properties for a given evidence using AbraFlexi library.
     *
     * @param string               $evidence Evidence name (e.g., 'banka', 'faktura-vydana')
     * @param array<string, mixed> $options  Connection options for RO instance
     *
     * @return array<string, mixed> Properties data indexed by field name
     */
    public static function loadProperties(string $evidence, array $options = []): array
    {
        if (isset(self::$cache[$evidence])) {
            return self::$cache[$evidence];
        }

        // Create RO instance if not exists
        if (self::$roInstance === null) {
            self::$roInstance = new RO(null, array_merge(['offline' => true], $options));
        }

        // Use the library's built-in method to get column info
        $properties = self::$roInstance->getColumnsInfo($evidence);

        if (!\is_array($properties)) {
            return [];
        }

        self::$cache[$evidence] = $properties;

        return $properties;
    }

    /**
     * Set connection options for online column info retrieval.
     *
     * @param array<string, mixed> $options Connection options
     */
    public static function setConnectionOptions(array $options): void
    {
        self::$roInstance = new RO(null, $options);
        self::$cache = []; // Clear cache when options change
    }

    /**
     * Get mandatory fields for an evidence.
     *
     * @param string $evidence Evidence name
     *
     * @return array<string, array<string, mixed>> Mandatory fields with their properties
     */
    public static function getMandatoryFields(string $evidence): array
    {
        $properties = self::loadProperties($evidence);
        $mandatory = [];

        foreach ($properties as $fieldName => $fieldProps) {
            if (
                isset($fieldProps['mandatory']) &&
                $fieldProps['mandatory'] === 'true' &&
                isset($fieldProps['isWritable']) &&
                $fieldProps['isWritable'] === 'true'
            ) {
                $mandatory[$fieldName] = $fieldProps;
            }
        }

        return $mandatory;
    }

    /**
     * Get writable fields for an evidence.
     *
     * @param string $evidence Evidence name
     *
     * @return array<string, array<string, mixed>> Writable fields with their properties
     */
    public static function getWritableFields(string $evidence): array
    {
        $properties = self::loadProperties($evidence);
        $writable = [];

        foreach ($properties as $fieldName => $fieldProps) {
            if (isset($fieldProps['isWritable']) && $fieldProps['isWritable'] === 'true') {
                $writable[$fieldName] = $fieldProps;
            }
        }

        return $writable;
    }

    /**
     * Get field info including human-readable name and type.
     *
     * @param string $evidence  Evidence name
     * @param string $fieldName Field name
     *
     * @return array<string, mixed>|null Field properties or null if not found
     */
    public static function getFieldInfo(string $evidence, string $fieldName): ?array
    {
        $properties = self::loadProperties($evidence);

        return $properties[$fieldName] ?? null;
    }

    /**
     * Get allowed values for a select field.
     *
     * @param string $evidence  Evidence name
     * @param string $fieldName Field name
     *
     * @return array<string, string> Allowed values (key => label)
     */
    public static function getSelectValues(string $evidence, string $fieldName): array
    {
        $fieldInfo = self::getFieldInfo($evidence, $fieldName);

        if ($fieldInfo === null || ($fieldInfo['type'] ?? '') !== 'select') {
            return [];
        }

        $values = [];

        if (isset($fieldInfo['values']['value']) && \is_array($fieldInfo['values']['value'])) {
            foreach ($fieldInfo['values']['value'] as $item) {
                if (isset($item['@key'])) {
                    $values[$item['@key']] = $item['$'] ?? $item['@key'];
                }
            }
        }

        return $values;
    }

    /**
     * Validate that all mandatory fields are present in data.
     *
     * @param string               $evidence Evidence name
     * @param array<string, mixed> $data     Data to validate
     *
     * @return array<string, array<string, mixed>> Missing mandatory fields with their properties
     */
    public static function getMissingMandatoryFields(string $evidence, array $data): array
    {
        $mandatory = self::getMandatoryFields($evidence);
        $missing = [];

        foreach ($mandatory as $fieldName => $fieldProps) {
            if (!isset($data[$fieldName]) || $data[$fieldName] === '' || $data[$fieldName] === null) {
                $missing[$fieldName] = $fieldProps;
            }
        }

        return $missing;
    }

    /**
     * Format field info for display.
     *
     * @param string               $fieldName  Field name
     * @param array<string, mixed> $fieldProps Field properties
     *
     * @return string Formatted field description
     */
    public static function formatFieldInfo(string $fieldName, array $fieldProps): string
    {
        $name = $fieldProps['name'] ?? $fieldName;
        $type = $fieldProps['type'] ?? 'unknown';
        $info = "{$fieldName} ({$name}) [{$type}]";

        if ($type === 'select' && isset($fieldProps['values']['value'])) {
            $allowedValues = [];

            foreach ($fieldProps['values']['value'] as $item) {
                if (isset($item['@key'])) {
                    $allowedValues[] = $item['@key'];
                }
            }

            if (!empty($allowedValues)) {
                $info .= ' - allowed: ' . implode(', ', $allowedValues);
            }
        }

        return $info;
    }
}
