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

// Processar criação de grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    if (empty($group_name)) {
        $error_message = 'O nome do grupo é obrigatório.';
    } else {
        try {
            $pdo->beginTransaction();
            $query = "INSERT INTO family_groups (name, created_by) VALUES (:name, :created_by) RETURNING id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['name' => $group_name, 'created_by' => $user_id]);
            $group_id = $stmt->fetchColumn();

            // Adicionar o criador como administrador
            $query = "INSERT INTO family_members (group_id, user_id, role) VALUES (:group_id, :user_id, 'admin')";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['group_id' => $group_id, 'user_id' => $user_id]);

            $pdo->commit();
            $success_message = 'Grupo criado com sucesso!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Erro ao criar grupo: ' . $e->getMessage();
        }
    }
}

// Processar adição de membro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $group_id = intval($_POST['group_id']);
    $email = trim($_POST['email']);
    if (empty($email)) {
        $error_message = 'O email do membro é obrigatório.';
    } else {
        try {
            // Verificar se o usuário existe
            $query = "SELECT id FROM users WHERE email = :email";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['email' => $email]);
            $member = $stmt->fetch();
            if (!$member) {
                $error_message = 'Usuário com este email não encontrado.';
            } else {
                // Verificar se o usuário já está no grupo
                $query = "SELECT id FROM family_members WHERE group_id = :group_id AND user_id = :user_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['group_id' => $group_id, 'user_id' => $member['id']]);
                if ($stmt->fetch()) {
                    $error_message = 'Este usuário já está no grupo.';
                } else {
                    // Adicionar membro
                    $query = "INSERT INTO family_members (group_id, user_id, role) VALUES (:group_id, :user_id, 'member')";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute(['group_id' => $group_id, 'user_id' => $member['id']]);
                    $success_message = 'Membro adicionado com sucesso!';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Erro ao adicionar membro: ' . $e->getMessage();
        }
    }
}

// Carregar grupos do usuário
try {
    $query = "SELECT g.id, g.name, g.created_by, fm.role 
              FROM family_groups g 
              JOIN family_members fm ON g.id = fm.group_id 
              WHERE fm.user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Erro ao carregar grupos: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos Familiares</title>
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
                    <li class="nav-item"><a class="nav-link" href="bills.php">Contas</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Grupos</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Grupos Familiares</h1>
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
                        <h5 class="card-title">Criar Novo Grupo</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nome do Grupo</label>
                                <input type="text" class="form-control" name="group_name" required>
                            </div>
                            <button type="submit" name="create_group" class="btn btn-primary">Criar</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Meus Grupos</h5>
                        <?php if (empty($groups)): ?>
                            <p>Nenhum grupo encontrado.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Papel</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($group['name']); ?></td>
                                            <td><?php echo $group['role'] === 'admin' ? 'Administrador' : 'Membro'; ?></td>
                                            <td>
                                                <?php if ($group['role'] === 'admin'): ?>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal<?php echo $group['id']; ?>">Adicionar Membro</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais para adicionar membros -->
    <?php foreach ($groups as $group): ?>
        <?php if ($group['role'] === 'admin'): ?>
            <div class="modal fade" id="addMemberModal<?php echo $group['id']; ?>" tabindex="-1" aria-labelledby="addMemberModalLabel<?php echo $group['id']; ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addMemberModalLabel<?php echo $group['id']; ?>">Adicionar Membro ao Grupo: <?php echo htmlspecialchars($group['name']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Email do Membro</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <button type="submit" name="add_member" class="btn btn-primary">Adicionar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>