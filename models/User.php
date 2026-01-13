<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function register($username, $password, $mobile_number) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, mobile_number) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $mobile_number);
        
        if ($stmt->execute()) {
            return ['success' => true, 'user_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, username, password, mobile_number, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                return ['success' => true, 'user' => $user];
            }
        }
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    public function getAllUsers() {
        $stmt = $this->conn->prepare("SELECT id, username, mobile_number, is_admin, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
    
    public function updateAdminStatus($user_id, $is_admin) {
        $stmt = $this->conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_admin, $user_id);
        return $stmt->execute();
    }
    
    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT id, username, mobile_number, is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function isAdmin($user_id) {
        $stmt = $this->conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        return $user && $user['is_admin'] == 1;
    }
    
    public function updateProfile($user_id, $username, $password = null, $mobile_number = null) {
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if ($mobile_number) {
                $stmt = $this->conn->prepare("UPDATE users SET username = ?, password = ?, mobile_number = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $hashed_password, $mobile_number, $user_id);
            } else {
                $stmt = $this->conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $hashed_password, $user_id);
            }
        } else {
            if ($mobile_number) {
                $stmt = $this->conn->prepare("UPDATE users SET username = ?, mobile_number = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $mobile_number, $user_id);
            } else {
                $stmt = $this->conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $username, $user_id);
            }
        }
        
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function checkUsernameExists($username, $exclude_user_id = null) {
        if ($exclude_user_id) {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $exclude_user_id);
        } else {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    public function createInitialAdmin($username, $password, $mobile_number = '0000000000') {
        // Check if user already exists
        if ($this->checkUsernameExists($username)) {
            return ['success' => false, 'error' => 'User already exists'];
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, mobile_number, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $username, $hashed_password, $mobile_number);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'user_id' => $this->conn->insert_id,
                'username' => $username
            ];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function userExists($username) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
