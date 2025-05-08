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
$success_message = '';
$error_message = '';

// Processar adição de conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bill'])) {
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $due_date = $_POST['due_date'];
    $recurring = isset($_POST['recurring']) ? 1 : 0;
    $recurrence_months = $recurring ? (isset($_POST['recurrence_months']) ? intval($_POST['recurrence_months']) : 0) : 0;

    if (empty($description) || $amount <= 0 || empty($due_date)) {
        $error_message = 'Preencha todos os campos corretamente.';
    } elseif ($recurring && ($recurrence_months < 1 || $recurrence_months > 12)) {
        $error_message = 'A quantidade de meses deve ser entre 1 e 12.';
    } else {
        try {
            $pdo->beginTransaction();
            $query = "INSERT INTO bills (user_id, description, amount, due_date, recurring) 
                      VALUES (:user_id, :description, :amount, :due_date, :recurring)";
            $stmt = $pdo->prepare($query);
            
            // Inserir a conta inicial
            $stmt->execute([
                'user_id' => $user_id,
                'description' => $description,
                'amount' => $amount,
                'due_date' => $due_date,
                'recurring' => $recurring
            ]);

            // Inserir contas recorrentes para os meses seguintes
            if ($recurring && $recurrence_months > 0) {
                $base_date = new DateTime($due_date);
                for ($i = 1; $i <= $recurrence_months; $i++) {
                    $next_date = clone $base_date;
                    $next_date->modify("+$i month");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'description' => $description,
                        'amount' => $amount,
                        'due_date' => $next_date->format('Y-m-d'),
                        'recurring' => $recurring
                    ]);
                }
            }

            $pdo->commit();
            $success_message = 'Conta(s) adicionada(s) com sucesso!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Erro ao adicionar conta: ' . $e->getMessage();
        }
    }
}

// Processar edição de conta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bill'])) {
    $bill_id = intval($_POST['bill_id']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $due_date = $_POST['due_date'];
    $recurring = isset($_POST['recurring']) ? 1 : 0;
    $status = $_POST['status'];

    if (empty($description) || $amount <= 0 || empty($due_date)) {
        $error_message = 'Preencha todos os campos corretamente.';
    } else {
        try {
            $query = "UPDATE bills SET description = :description, amount = :amount, due_date = :due_date, 
                      recurring = :recurring, status = :status WHERE id = :id AND user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'id' => $bill_id,
                'user_id' => $user_id,
                'description' => $description,
                'amount' => $amount,
                'due_date' => $due_date,
                'recurring' => $recurring,
                'status' => $status
            ]);
            $success_message = 'Conta atualizada com sucesso!';
        } catch (PDOException $e) {
            $error_message = 'Erro ao atualizar conta: ' . $e->getMessage();
        }
    }
}

// Processar exclusão de conta
if (isset($_GET['delete'])) {
    $bill_id = intval($_GET['delete']);
    try {
        $query = "DELETE FROM bills WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
        $success_message = 'Conta excluída com sucesso!';
    } catch (PDOException $e) {
        $error_message = 'Erro ao excluir conta: ' . $e->getMessage();
    }
}

// Carregar conta para edição
$edit_bill = null;
if (isset($_GET['edit'])) {
    $bill_id = intval($_GET['edit']);
    try {
        $query = "SELECT * FROM bills WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
        $edit_bill = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = 'Erro ao carregar conta para edição: ' . $e->getMessage();
    }
}

// Carregar contas (com filtro por data, se fornecido)
$filter_date = isset($_GET['date']) ? $_GET['date'] : null;
try {
    $query = "SELECT * FROM bills WHERE user_id = :user_id";
    $params = ['user_id' => $user_id];
    if ($filter_date) {
        $query .= " AND due_date = :due_date";
        $params['due_date'] = $filter_date;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Erro ao carregar contas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Contas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #12222F;">
        <div class="container">
            <a class="navbar-brand" href="index.php">Gestão Financeira</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Contas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Grupos</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Gerenciar Contas <?php echo $filter_date ? ' - ' . date('d/m/Y', strtotime($filter_date)) : ''; ?></h1>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $edit_bill ? 'Editar Conta' : 'Adicionar Conta'; ?></h5>
                        <form method="POST">
                            <?php if ($edit_bill): ?>
                                <input type="hidden" name="bill_id" value="<?php echo $edit_bill['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" class="form-control" name="description" value="<?php echo $edit_bill ? htmlspecialchars($edit_bill['description']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valor</label>
                                <input type="number" step="0.01" class="form-control" name="amount" value="<?php echo $edit_bill ? $edit_bill['amount'] : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data de Vencimento</label>
                                <input type="date" class="form-control" name="due_date" value="<?php echo $edit_bill ? $edit_bill['due_date'] : ($filter_date ?: ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Recorrente?</label>
                                <input type="checkbox" id="recurring" name="recurring" <?php echo $edit_bill && $edit_bill['recurring'] ? 'checked' : ''; ?>>
                            </div>
                            <div class="mb-3" id="recurrence_months_div" style="display: <?php echo $edit_bill && $edit_bill['recurring'] ? 'block' : 'none'; ?>;">
                                <label class="form-label">Quantidade de Meses</label>
                                <input type="number" class="form-control" name="recurrence_months" min="1" max="12" value="1" <?php echo $edit_bill ? 'disabled' : ''; ?>>
                                <small class="form-text text-muted">Número de meses para repetir a conta (1 a 12). Aplicável apenas ao adicionar.</small>
                            </div>
                            <?php if ($edit_bill): ?>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="pendente" <?php echo $edit_bill && $edit_bill['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="pago" <?php echo $edit_bill && $edit_bill['status'] === 'pago' ? 'selected' : ''; ?>>Pago</option>
                                        <option value="atrasado" <?php echo $edit_bill && $edit_bill['status'] === 'atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <button type="submit" name="<?php echo $edit_bill ? 'edit_bill' : 'add_bill'; ?>" class="btn btn-primary">Salvar</button>
                            <?php if ($edit_bill): ?>
                                <a href="bills.php<?php echo $filter_date ? '?date=' . $filter_date : ''; ?>" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Contas <?php echo $filter_date ? 'de ' . date('d/m/Y', strtotime($filter_date)) : 'do Mês'; ?></h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bill['description']); ?></td>
                                        <td>R$ <?php echo number_format($bill['amount'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($bill['due_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($bill['status']); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $bill['id'] . ($filter_date ? '&date=' . $filter_date : ''); ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <a href="?delete=<?php echo $bill['id'] . ($filter_date ? '&date=' . $filter_date : ''); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja excluir esta conta?')">Excluir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/esconder campo de meses com base no checkbox de recorrência
        document.getElementById('recurring').addEventListener('change', function() {
            document.getElementById('recurrence_months_div').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>