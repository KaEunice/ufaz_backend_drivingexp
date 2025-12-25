<?php
class SafeMySQLi {
    private $connection;
    
    public function __construct($host, $user, $pass, $db) {
        $this->connection = new mysqli($host, $user, $pass, $db);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
    }
    
    public function query($sql, $params = [], $types = "") {
        $stmt = $this->connection->prepare($sql);
        
        if (!$stmt) {
            error_log("MySQL Prepare Error: " . $this->connection->error);
            return false;
        }
        
        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat("s", count($params));
            }
            $stmt->bind_param($types, ...$params);
        }
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("MySQL Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        if (stripos(trim($sql), 'SELECT') === 0) {
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        } 
        else {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            return $affected_rows > 0;
        }
    }
    
    public function insert($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $values = array_values($data);
        $types = $this->getParamTypes($values);
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        return $this->query($sql, $values, $types);
    }
    
    private function getParamTypes($values) {
        $types = "";
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= "i";
            } elseif (is_float($value)) {
                $types .= "d";
            } else {
                $types .= "s";
            }
        }
        return $types;
    }
    
    public static function anonymize($data, $salt = ANONYMIZE_SALT) {
        return hash('sha256', $data . $salt);
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getInsertId() {
        return $this->connection->insert_id;
    }
    
    public function close() {
        $this->connection->close();
    }
}
?>
