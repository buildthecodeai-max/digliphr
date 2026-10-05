<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        if ($this->softDeletes) {
            $sql .= ' AND `deleted_at` IS NULL';
        }
        $sql .= ' LIMIT 1';

        $row = $this->db->fetch($sql, ['id' => $id]);
        return $row ? $this->hide($row) : null;
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value";
        if ($this->softDeletes) {
            $sql .= ' AND `deleted_at` IS NULL';
        }
        $sql .= ' LIMIT 1';

        $row = $this->db->fetch($sql, ['value' => $value]);
        return $row ? $this->hide($row) : null;
    }

    public function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($this->softDeletes) {
            $sql .= ' WHERE `deleted_at` IS NULL';
        }
        $sql .= " ORDER BY `{$orderBy}` {$direction}";

        return array_map(fn ($row) => $this->hide($row), $this->db->fetchAll($sql));
    }

    public function where(array $conditions, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $clauses = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $clauses[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $clauses);
        if ($this->softDeletes) {
            $sql .= ' AND `deleted_at` IS NULL';
        }
        $sql .= " ORDER BY `{$orderBy}` {$direction}";

        return array_map(fn ($row) => $this->hide($row), $this->db->fetchAll($sql, $params));
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }
        return $this->db->insert($this->table, $data);
    }

    public function update(int|string $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return $this->db->update($this->table, $data, "`{$this->primaryKey}` = :_id", ['_id' => $id]) >= 0;
    }

    public function delete(int|string $id): bool
    {
        if ($this->softDeletes) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        return $this->db->delete($this->table, "`{$this->primaryKey}` = :id", ['id' => $id]) > 0;
    }

    public function restore(int|string $id): bool
    {
        if (!$this->softDeletes) {
            return false;
        }
        return $this->db->update($this->table, ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')], "`{$this->primaryKey}` = :id", ['id' => $id]) > 0;
    }

    public function paginate(int $page = 1, int $perPage = 15, array $conditions = [], string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $clauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value) && isset($value['op'], $value['value'])) {
                $param = str_replace('.', '_', $column) . '_p';
                $clauses[] = "`{$column}` {$value['op']} :{$param}";
                $params[$param] = $value['value'];
            } elseif (is_array($value) && ($value['op'] ?? null) === 'LIKE') {
                $param = str_replace('.', '_', $column) . '_p';
                $clauses[] = "`{$column}` LIKE :{$param}";
                $params[$param] = $value['value'];
            } else {
                $param = str_replace('.', '_', $column);
                $clauses[] = "`{$column}` = :{$param}";
                $params[$param] = $value;
            }
        }

        if ($this->softDeletes) {
            $clauses[] = '`deleted_at` IS NULL';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` {$where} ORDER BY `{$orderBy}` {$direction} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => array_map(fn ($row) => $this->hide($row), $rows),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function count(array $conditions = []): int
    {
        $clauses = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $clauses[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }
        if ($this->softDeletes) {
            $clauses[] = '`deleted_at` IS NULL';
        }
        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` {$where}", $params);
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function hide(array $row): array
    {
        foreach ($this->hidden as $field) {
            unset($row[$field]);
        }
        return $row;
    }

    public function raw(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    public function rawOne(string $sql, array $params = []): ?array
    {
        return $this->db->fetch($sql, $params);
    }

    public function db(): Database
    {
        return $this->db;
    }
}
