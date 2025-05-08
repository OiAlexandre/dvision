<?php
session_start();
require_once '../api/conexao.php';
require '../vendor/autoload.php'; // Necessário para JWT (instalar via Composer: firebase/php-jwt)

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = 'hXkNzklLDgAjMxPdtQ7qAClAxNEMqqzDlAvyF0AP2rY='; // Substitua por uma chave segura

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];
    $password = $data['password'];

    try {
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $payload = [
                'iat' => time(),
                'exp' => time() + 3600, // Token válido por 1 hora
                'user_id' => $user['id']
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');
            $_SESSION['user_id'] = $user['id'];
            echo json_encode(['success' => true, 'token' => $jwt]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>