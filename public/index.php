<?php
session_start();
require_once '../api/conexao.php';
require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = 'hXkNzklLDgAjMxPdtQ7qAClAxNEMqqzDlAvyF0AP2rY='; // Mesma chave do login.php

// Verificar autenticação
$token = isset($_SESSION['token']) ? $_SESSION['token'] : null;
if (!$token || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    if ($decoded->user_id != $_SESSION['user_id']) {
        throw new Exception('Usuário inválido');
    }
} catch (Exception $e) {
    unset($_SESSION['user_id']);
    unset($_SESSION['token']);
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Configuração do calendário
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$day_of_week = date('w', $first_day);
$month_name = date('F', $first_day);

// Ajustar navegação de meses
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;

// Carregar contas do mês
try {
    $start_date = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $end_date = date('Y-m-d', mktime(0, 0, 0, $month, $days_in_month, $year));
    $query = "SELECT * FROM bills WHERE user_id = :user_id AND due_date BETWEEN :start_date AND :end_date";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id, 'start_date' => $start_date, 'end_date' => $end_date]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar contas por dia
    $bills_by_day = [];
    foreach ($bills as $bill) {
        $day = date('j', strtotime($bill['due_date']));
        $bills_by_day[$day][] = $bill;
    }
} catch (PDOException $e) {
    $error_message = 'Erro ao carregar contas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar-table th, .calendar-table td {
            border: 1px solid #ddd;
            text-align: center;
            padding: 10px;
            vertical-align: top;
            height: 100px;
        }
        .calendar-table th {
            background-color: #f8f9fa;
        }
        .has-bills {
            background-color: #e6f3ff;
        }
        .bill-tooltip {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #12222F;">
        <div class="container">
            <a class="navbar-brand" href="#">Gestão Financeira</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="bills.php">Contas</a></li>
                    <li class="nav-item"><a class="nav-link" href="groups.php">Grupos</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Bem-vindo à Gestão Financeira</h1>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Calendário de Contas - <?php echo $month_name . ' ' . $year; ?></h5>
                        <div class="d-flex justify-content-between mb-3">
                            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-secondary">Mês Anterior</a>
                            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-secondary">Próximo Mês</a>
                        </div>
                        <table class="calendar-table">
                            <thead>
                                <tr>
                                    <th>Dom</th>
                                    <th>Seg</th>
                                    <th>Ter</th>
                                    <th>Qua</th>
                                    <th>Qui</th>
                                    <th>Sex</th>
                                    <th>Sáb</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $day_count = 1;
                                $week = [];
                                // Preencher dias vazios antes do início do mês
                                for ($i = 0; $i < $day_of_week; $i++) {
                                    $week[] = '<td></td>';
                                }
                                // Preencher dias do mês
                                while ($day_count <= $days_in_month) {
                                    if (count($week) == 7) {
                                        echo '<tr>' . implode('', $week) . '</tr>';
                                        $week = [];
                                    }
                                    $current_day = sprintf('%04d-%02d-%02d', $year, $month, $day_count);
                                    $has_bills = isset($bills_by_day[$day_count]);
                                    $cell_class = $has_bills ? 'has-bills' : '';
                                    $tooltip_content = '';
                                    if ($has_bills) {
                                        $tooltip_content .= '<div class="bill-tooltip">';
                                        foreach ($bills_by_day[$day_count] as $bill) {
                                            $tooltip_content .= htmlspecialchars($bill['description']) . ': R$ ' . number_format($bill['amount'], 2, ',', '.') . '<br>';
                                        }
                                        $tooltip_content .= '</div>';
                                    }
                                    $week[] = '<td class="' . $cell_class . '" data-bs-toggle="tooltip" data-bs-html="true" title="' . htmlspecialchars($tooltip_content) . '">
                                        <a href="bills.php?date=' . $current_day . '">' . $day_count . '</a>
                                    </td>';
                                    $day_count++;
                                }
                                // Preencher dias vazios no final
                                while (count($week) < 7) {
                                    $week[] = '<td></td>';
                                }
                                echo '<tr>' . implode('', $week) . '</tr>';
                                ?>
                            </tbody>
                        </table>
                        <a href="bills.php" class="btn btn-primary mt-3">Gerenciar Contas</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Habilitar tooltips do Bootstrap
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>
</body>
</html>