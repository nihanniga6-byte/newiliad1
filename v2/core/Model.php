<?php
/**
 * Base Model Class
 * 
 * Provides common database operations for all models.
 * All queries use prepared statements to prevent SQL injection.
 * 
 * @package Core
 */

class Model
{
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find record by primary key
     * 
     * @param int $id Record ID
     * @return array|null Record or null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Find one record by column value
     * 
     * @param string $column Column name
     * @param mixed $value Column value
     * @return array|null Record or null
     */
    public function findOneBy(string $column, $value): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT 1",
            ['value' => $value]
        );
    }

    /**
     * Find records by column value
     * 
     * @param string $column Column name
     * @param mixed $value Column value
     * @param int $limit Maximum records to return
     * @return array Array of records
     */
    public function findBy(string $column, $value, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT :limit",
            ['value' => $value, 'limit' => $limit]
        );
    }

    /**
     * Get all records
     * 
     * @param int $limit Maximum records
     * @param string $orderBy Order by clause
     * @return array Array of records
     */
    public function all(int $limit = 100, string $orderBy = 'id DESC'): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT :limit",
            ['limit' => $limit]
        );
    }

    /**
     * Get paginated records
     * 
     * @param int $page Current page
     * @param int $perPage Records per page
     * @param string $orderBy Order by clause
     * @return array Paginated results with metadata
     */
    public function paginate(int $page = 1, int $perPage = 50, string $orderBy = 'id DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count();
        
        $records = $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset",
            ['limit' => $perPage, 'offset' => $offset]
        );

        return [
            'data' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'has_next' => $page < ceil($total / $perPage),
            'has_prev' => $page > 1
        ];
    }

    /**
     * Create new record
     * 
     * @param array $data Column => Value pairs
     * @return int Last insert ID
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * Update record by ID
     * 
     * @param int $id Record ID
     * @param array $data Column => Value pairs
     * @return int Number of affected rows
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update(
            $this->table,
            $data,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Delete record by ID
     * 
     * @param int $id Record ID
     * @return int Number of deleted rows
     */
    public function delete(int $id): int
    {
        return $this->db->delete(
            $this->table,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * Count records
     * 
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return int Count
     */
    public function count(string $where = "1=1", array $params = []): int
    {
        return $this->db->count($this->table, $where, $params);
    }

    /**
     * Check if record exists
     * 
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return bool True if exists
     */
    public function exists(string $where, array $params = []): bool
    {
        return $this->count($where, $params) > 0;
    }

    /**
     * Search records by keyword
     * 
     * @param string $keyword Search term
     * @param array $columns Columns to search
     * @param int $limit Maximum results
     * @return array Matching records
     */
    public function search(string $keyword, array $columns, int $limit = 50): array
    {
        $conditions = [];
        $params = ['keyword' => "%{$keyword}%", 'limit' => $limit];

        foreach ($columns as $column) {
            $conditions[] = "{$column} LIKE :keyword";
        }

        $where = implode(' OR ', $conditions);

        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$where} LIMIT :limit",
            $params
        );
    }

    /**
     * Perform a custom query
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return PDOStatement Statement
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        return $this->db->query($sql, $params);
    }
}
