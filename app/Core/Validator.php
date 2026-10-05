<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules
    ) {
        $this->validate();
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $method = 'rule' . str_replace(' ', '', ucwords(str_replace('_', ' ', $rule)));
                if (method_exists($this, $method)) {
                    $this->{$method}($field, $value, $params);
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function ruleRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, $this->label($field) . ' is required.');
        }
    }

    private function ruleEmail(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $this->label($field) . ' must be a valid email address.');
        }
    }

    private function ruleMin(string $field, mixed $value, array $params): void
    {
        $min = (float) ($params[0] ?? 0);
        if ($value === null || $value === '') {
            return;
        }

        // numeric|min / integer|min compare numeric value (POST fields arrive as strings).
        if ($this->fieldHasRule($field, ['numeric', 'integer']) && is_numeric($value)) {
            if ((float) $value < $min) {
                $this->addError($field, $this->label($field) . " must be at least {$min}.");
            }
            return;
        }

        // Strings (including phone/code) use character length.
        if (is_string($value)) {
            if (mb_strlen($value) < (int) $min) {
                $this->addError($field, $this->label($field) . ' must be at least ' . (int) $min . ' characters.');
            }
            return;
        }

        if (is_int($value) || is_float($value)) {
            if ((float) $value < $min) {
                $this->addError($field, $this->label($field) . " must be at least {$min}.");
            }
        }
    }

    private function ruleMax(string $field, mixed $value, array $params): void
    {
        $max = (float) ($params[0] ?? 0);
        if ($value === null || $value === '') {
            return;
        }

        // numeric|max / integer|max compare numeric value (POST fields arrive as strings).
        if ($this->fieldHasRule($field, ['numeric', 'integer']) && is_numeric($value)) {
            if ((float) $value > $max) {
                $this->addError($field, $this->label($field) . " must not exceed {$max}.");
            }
            return;
        }

        // Strings (including phone/code) use character length.
        if (is_string($value)) {
            if (mb_strlen($value) > (int) $max) {
                $this->addError($field, $this->label($field) . ' must not exceed ' . (int) $max . ' characters.');
            }
            return;
        }

        if (is_int($value) || is_float($value)) {
            if ((float) $value > $max) {
                $this->addError($field, $this->label($field) . " must not exceed {$max}.");
            }
        }
    }

    /** @param list<string> $names */
    private function fieldHasRule(string $field, array $names): bool
    {
        $ruleString = $this->rules[$field] ?? '';
        $rules = is_array($ruleString) ? $ruleString : explode('|', (string) $ruleString);

        foreach ($rules as $rule) {
            $name = str_contains((string) $rule, ':')
                ? explode(':', (string) $rule, 2)[0]
                : (string) $rule;
            if (in_array($name, $names, true)) {
                return true;
            }
        }

        return false;
    }

    private function ruleNumeric(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, $this->label($field) . ' must be numeric.');
        }
    }

    private function ruleInteger(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $this->label($field) . ' must be an integer.');
        }
    }

    private function ruleDate(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
            $this->addError($field, $this->label($field) . ' must be a valid date.');
        }
    }

    private function ruleIn(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !in_array((string) $value, $params, true)) {
            $this->addError($field, $this->label($field) . ' has an invalid value.');
        }
    }

    private function ruleConfirmed(string $field, mixed $value, array $params): void
    {
        $confirmation = $this->data[$field . '_confirmation'] ?? null;
        if ($value !== $confirmation) {
            $this->addError($field, $this->label($field) . ' confirmation does not match.');
        }
    }

    /**
     * unique:table,column[,exceptId[,whereColumn,whereValue]]
     * Example (per-company): unique:departments,code,12,company_id,3
     */
    private function ruleUnique(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $exceptId = $params[2] ?? null;
        $whereColumn = $params[3] ?? null;
        $whereValue = $params[4] ?? null;

        if ($table === '') {
            return;
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        $bind = ['value' => $value];

        if ($exceptId !== null && $exceptId !== '') {
            $sql .= ' AND `id` != :except';
            $bind['except'] = $exceptId;
        }

        if ($whereColumn !== null && $whereColumn !== '') {
            $sql .= " AND `{$whereColumn}` = :where_val";
            $bind['where_val'] = $whereValue;
        }

        $count = (int) Database::getInstance()->fetchColumn($sql, $bind);
        if ($count > 0) {
            $this->addError($field, $this->label($field) . ' has already been taken.');
        }
    }

    private function ruleExists(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $table = $params[0] ?? '';
        $column = $params[1] ?? 'id';

        $count = (int) Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value",
            ['value' => $value]
        );

        if ($count === 0) {
            $this->addError($field, $this->label($field) . ' is invalid.');
        }
    }

    private function ruleNullable(string $field, mixed $value, array $params): void
    {
        // Marker rule – no action
    }

    private function ruleBoolean(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
            $this->addError($field, $this->label($field) . ' must be true or false.');
        }
    }

    private function ruleUrl(string $field, mixed $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $this->label($field) . ' must be a valid URL.');
        }
    }

    private function ruleRegex(string $field, mixed $value, array $params): void
    {
        $pattern = $params[0] ?? '';
        if ($value !== null && $value !== '' && $pattern !== '' && !preg_match($pattern, (string) $value)) {
            $this->addError($field, $this->label($field) . ' format is invalid.');
        }
    }

    private function ruleAfterOrEqual(string $field, mixed $value, array $params): void
    {
        $other = $params[0] ?? '';
        $otherValue = $this->data[$other] ?? $other;
        if ($value && $otherValue && strtotime((string) $value) < strtotime((string) $otherValue)) {
            $this->addError($field, $this->label($field) . ' must be after or equal to ' . $this->label($other) . '.');
        }
    }

    private function ruleBeforeOrEqual(string $field, mixed $value, array $params): void
    {
        $other = $params[0] ?? '';
        $otherValue = $this->data[$other] ?? $other;
        if ($value && $otherValue && strtotime((string) $value) > strtotime((string) $otherValue)) {
            $this->addError($field, $this->label($field) . ' must be before or equal to ' . $this->label($other) . '.');
        }
    }

    private function label(string $field): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }
}
