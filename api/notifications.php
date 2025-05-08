<?php
require_once 'conexao.php';
require '../vendor/autoload.php'; // Necessário para Twilio (instalar via Composer)

use Twilio\Rest\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // ou o caminho da raiz do projeto
$dotenv->load();

// Configurações do Twilio ($sid está no .env asd)
$sid = $_ENV['TWILIO_SID'];
$token = "[AuthToken]";
$twilio = new Client($sid, $token);
$fromNumber = 'whatsapp:+14155238886'; // Número do Twilio para WhatsApp

// Buscar contas próximas ao vencimento
$today = new DateTime();
$days = [0, 3, 5]; // Notificações no dia, 3 dias e 5 dias antes

foreach ($days as $day) {
    $targetDate = (clone $today)->modify("+$day days")->format('Y-m-d');
    $query = "SELECT b.*, u.phone, u.name 
              FROM bills b 
              JOIN users u ON b.user_id = u.id 
              WHERE b.due_date = :due_date AND b.status = 'pendente'";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['due_date' => $targetDate]);
    $bills = $stmt->fetchAll();

    foreach ($bills as $bill) {
        $message = "Olá {$bill['name']}, lembrete: a conta '{$bill['description']}' de R$ {$bill['amount']} vence em {$bill['due_date']}.";
        try {
            $twilio->messages->create(
                "whatsapp:{$bill['phone']}",
                [
                    'from' => $fromNumber,
                    'body' => $message
                ]
            );

            // Registrar notificação no banco
            $query = "INSERT INTO notifications (bill_id, user_id, message, sent_at) 
                      VALUES (:bill_id, :user_id, :message, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'bill_id' => $bill['id'],
                'user_id' => $bill['user_id'],
                'message' => $message
            ]);
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação: " . $e->getMessage());
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Notificações processadas']);
?>