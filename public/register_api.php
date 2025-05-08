<?php
require_once '../api/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'];
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $phone = $data['phone'];

    try {
        $query = "INSERT INTO users (name, email, password, phone) VALUES (:name, :email, :password, :phone)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'phone' => $phone
        ]);
        echo json_encode(['success' => true, 'message' => 'Usuário registrado com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
