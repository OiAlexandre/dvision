<?php
// Configurações do banco de dados
$host = 'ep-white-sunset-a4xwtmau-pooler.us-east-1.aws.neon.tech';
$port = '5432'; 
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_bxJcw6E8rdgo';
$endpoint_id = 'ep-white-sunset-a4xwtmau'; 

// String de conexão com o parâmetro options
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require;options=endpoint=$endpoint_id";

try {
    // Criar conexão com o banco de dados usando PDO
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
    ]);
     //echo "Conexão bem-sucedida!"; 
} catch (PDOException $e) {
    // Exibir erro caso a conexão falhe
    die("Erro na conexão: " . $e->getMessage());
}
?>