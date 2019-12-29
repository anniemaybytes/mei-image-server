<?php declare(strict_types=1);

namespace Mei\Utilities;

use PDO;

/**
 * Class PDOParamMapper
 *
 * @package Mei\Utilities
 */
class PDOParamMapper
{
    /**
     * @param string entityAttrType
     *
     * @return int
     */
    public static function map(string $entityAttrType): int
    {
        switch ($entityAttrType) {
            case 'int':
                return PDO::PARAM_INT;
            case 'bool':
                return PDO::PARAM_BOOL;
            default:
                return PDO::PARAM_STR;
        }
    }
}
