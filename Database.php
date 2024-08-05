<?php

namespace DevTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private static string $SKIP = '__SKIP__';

    /**
     * Конструктор класу Database.
     *
     * @param mysqli $mysqli Об'єкт з'єднання з базою даних MySQLi.
     */
    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Побудова запиту з обробкою заповнювачів і умовних блоків.
     *
     * @param string $query Шаблон запиту з заповнювачами.
     * @param array  $args  Аргументи для заміни заповнювачів.
     *
     * @return string Оброблений запит.
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $query = $this->processPlaceholders($query, $args);
        return $this->processConditionBlocks($query);
    }

    /**
     * Отримання значення для пропуску умовного блоку.
     *
     * @return string Значення для пропуску.
     */
    public function skip(): string
    {
        return self::$SKIP;
    }

    /**
     * Обробка умовних блоків у запиті.
     *
     * @param string $query Шаблон запиту.
     *
     * @return string Оброблений запит.
     */
    private function processConditionBlocks(string $query): string
    {
        return preg_replace(['/\\{[^}]*' . self::$SKIP . '[^}]*\\}/', '/\\{([^{}]+)\\}/'], ['', '$1'], $query);
    }

    /**
     * Обробка заповнювачів у запиті.
     *
     * @param string $query Шаблон запиту.
     * @param array  $args  Аргументи для заміни заповнювачів.
     *
     * @return string Оброблений запит.
     */
    private function processPlaceholders(string $query, array $args): string
    {
        $query = preg_replace_callback(
            '/\?([dfa#])|\?/',
            function ($matches) use (&$args) {
                $specificator = $matches[1] ?? null;
                $value = array_shift($args);

                if ($value === self::$SKIP) {
                    return self::$SKIP;
                }

                return match ($specificator) {
                    null => $this->buildValue($value),
                    'd' => $this->buildValue((int)$value),
                    'f' => $this->buildValue((float)$value),
                    '#' => $this->buildIdentifier($value),
                    'a' => $this->buildArray($value),
                    default => throw new Exception('Невірний специфікатор ' . $specificator)
                };
            },
            $query
        );

        return $query;
    }

    /**
     * Побудова ідентифікатора для використання в запиті.
     *
     * @param mixed $identifier Ідентифікатор для екранування.
     *
     * @return string Екранований ідентифікатор.
     */
    private function buildIdentifier(mixed $identifier): string
    {
        if (is_array($identifier)) {
            return implode(', ', array_map([$this, 'buildIdentifier'], $identifier));
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Обробка масиву для використання в запиті.
     *
     * @param array $array Масив для обробки.
     *
     * @return string Оброблений масив.
     */
    private function buildArray(array $array): string
    {
        if (array_is_list($array)) {
            return implode(', ', array_map([$this, 'buildValue'], $array));
        }

        return implode(', ', array_map(
            fn($k, $v) => $this->buildIdentifier($k) . ' = ' . $this->buildValue($v),
            array_keys($array),
            $array
        ));
    }

    /**
     * Побудова значення для використання в запиті.
     *
     * @param mixed $value Значення для екранування.
     *
     * @return string Екрановане значення.
     *
     * @throws Exception Якщо тип значення не підтримується.
     */
    private function buildValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return '\'' . $this->mysqli->real_escape_string($value) . '\'';
        } elseif (is_int($value) || is_float($value)) {
            return (string)$value;
        } elseif (is_array($value)) {
            return $this->buildArray($value);
        }

        throw new Exception("Непідтримуваний тип значення: " . gettype($value));
    }
}
