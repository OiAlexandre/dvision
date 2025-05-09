<?php
session_start();

// Limpar dados de todas as variáveis de sessão
$_SESSION = [];

session_destroy(); // Destruir a sessão
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sair - Gestão Financeira</title>
</head>
<body>
    <script>
        localStorage.removeItem('token'); // Limpar o token do localStorage
        localStorage.removeItem('user_id'); // Limpar o usuário do localStorage

        // Redirecionar para a página de login após 2 segundos
        setTimeout(function() {
            window.location.href = 'login.php'; // Altere para o caminho correto do seu arquivo de login
        }, 2000);
    </script>
    
</body>
</html>