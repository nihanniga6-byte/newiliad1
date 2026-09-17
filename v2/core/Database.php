<?php
/**
 * Database Singleton Class
 * 
 * Manages PDO connection with singleton pattern to prevent
 * multiple connections (critical for shared hosting limits).
 * 
 * @package Core
 */

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private int $queryCount = 0;

    /**
     * Private constructor - prevents direct instantiation
     * 
     * Creates PDO connection with secure settings.
     */
    private function __construct()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s;port=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET,
                DB_PORT
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Use real prepared statements
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT         => false, // Avoid persistent connections on shared hosting
                PDO::ATTR_TIMEOUT            => 5,     // 5 second connection timeout
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            Logger::error("Database connection failed: " . $e->getMessage());
            
            if (APP_ENV === 'development') {
                die("Database Error: " . $e->getMessage());
            } else {
                die("A database error occurred. Please try again later.");
            }
        }
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Get single instance of database
     * 
     * @return Database Singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     * 
     * @return PDO PDO connection object
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a prepared statement with parameters
     * 
     * Anti-SQL Injection: Always use this method for queries.
     * Uses named placeholders for better readability.
     * 
     * @param string $sql SQL query with named placeholders (e.g., :name)
     * @param array $params Parameter values
     * @return PDOStatement Statement object
     * @throws PDOException On query failure
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->queryCount++;
            return $stmt;
        } catch (PDOException $e) {
            Logger::error("Query failed: " . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Fetch single row
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array|null Single row or null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array Array of rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert record and return last insert ID
     * 
     * @param string $table Table name
     * @param array $data Column => Value pairs
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update records
     * 
     * @param string $table Table name
     * @param array $data Column => Value pairs to update
     * @param string $where WHERE clause with named placeholders
     * @param array $whereParams WHERE clause parameters
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        // Add updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');

        $set = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return int Number of deleted rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Count rows
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return int Row count
     */
    public function count(string $table, string $where = "1=1", array $params = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}";
        $result = $this->fetchOne($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Check if record exists
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters
     * @return bool True if exists
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get query count (for debugging)
     * 
     * @return int Number of queries executed
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Get last error
     * 
     * @return array Error information
     */
    public function getLastError(): array
    {
        $errorInfo = $this->pdo->errorInfo();
        return [
            'code' => $errorInfo[0] ?? '00000',
            'message' => $errorInfo[2] ?? 'No error'
        ];
    }
}
