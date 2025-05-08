<?php
// Habilitar depuração
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/laragon/logs/php_errors.log'); // Ajuste se necessário

session_start();
header('Content-Type: application/json; charset=utf-8', true);
ob_start();

// Verificar inclusão de arquivos
$conexao_path = realpath(__DIR__ . '/../api/conexao.php');
$vendor_path = realpath(__DIR__ . '/../vendor/autoload.php');

if (!$conexao_path || !file_exists($conexao_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Arquivo conexão.php não encontrado']);
    ob_end_flush();
    exit;
}

if (!$vendor_path || !file_exists($vendor_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'vendor/autoload.php não encontrado. Execute: composer require firebase/php-jwt']);
    ob_end_flush();
    exit;
}

require_once $conexao_path;
require_once $vendor_path;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = 'hXkNzklLDgAjMxPdtQ7qAClAxNEMqqzDlAvyF0AP2rY='; // Substitua por uma chave segura

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
        ob_end_flush();
        exit;
    }

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
                'exp' => time() + 3600,
                'user_id' => $user['id']
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['token'] = $jwt;
            http_response_code(200);
            echo json_encode(['success' => true, 'token' => $jwt, 'user_id' => $user['id']]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro no banco: ' . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Para GET, renderizar o formulário HTML
ob_end_clean();
header('Content-Type: text/html; charset=utf-8', true);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão Financeira</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center">Login</h3>
                        <form id="loginForm">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" id="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>
                        <p class="mt-3 text-center">Não tem conta? <a href="register.php">Cadastre-se</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const credentials = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value
            };
            console.log('Enviando:', credentials);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(credentials)
                });
                const text = await response.text();
                console.log('Resposta bruta:', text);
                try {
                    const result = JSON.parse(text);
                    if (result.success) {
                        localStorage.setItem('token', result.token);
                        localStorage.setItem('user_id', result.user_id);
                        window.location.href = 'index.php';
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    console.error('Resposta não é JSON:', text);
                    alert('Erro ao fazer login: Resposta inválida do servidor');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('Erro ao fazer login: ' + error.message);
            }
        });
    </script>
</body>
</html>