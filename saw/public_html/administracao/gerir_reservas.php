<?php
// Inclui o arquivo de configuração para conectar ao banco de dados
include '../config.php';
include '../verificar_bloqueio.php';
// Verifica se a variável $conn foi definida
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

date_default_timezone_set('Europe/Lisbon');

// Inicia a sessão
session_start();

// Função para verificar se o usuário logado é o admin principal
function isAdminPrincipal($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE (login = ? OR nome = ?) AND numero = ?");
    $stmt->execute(['admin', 'admin', $user_id]);
    return $stmt->fetch() !== false;
}

// Função para verificar o nível do usuário logado
function getUserLevel($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT nivel FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? (int)$user['nivel'] : null;
}

// Função para obter o nome do usuário
function getUserName($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? $user['nome'] : null;
}

// Função para registrar logs
function logAction($message) {
    $diretorioLogs = __DIR__ . '/../../logs';
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    $arquivoLog = $diretorioLogs . '/remocoes_reservas_admins.log';
    $dataHora = date('Y-m-d H:i:s');
    $logMessage = "[$dataHora] $message\n";
    file_put_contents($arquivoLog, $logMessage, FILE_APPEND);
}



// Função para excluir reservas antigas automaticamente
function deleteOldReservations() {
    global $conn;
    $dataHora = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = getUserName($user_id);
    $current_date = date('Y-m-d'); // Data atual no formato 'YYYY-MM-DD'
    $stmt = $conn->prepare("DELETE FROM reservas WHERE data_reserva < ?");
    if ($stmt->execute([$current_date])) {
        logAction(" Reservas antigas removidas automaticamente de todos os Users pelo Admin $user_name.");
    } else {
        logAction("Erro ao remover reservas antigas.");
    }
}

// Função para remover uma reserva
function removeReservation($id_reserva, $user_id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM reservas WHERE id = ?");
    if ($stmt->execute([$id_reserva])) {
        $user_name = getUserName($user_id);
        $dataHora = date('Y-m-d H:i:s');
        logAction("  O Admin {$user_name} (ID: {$user_id}) removeu a reserva com ID {$id_reserva}.");
        return true;
    }
    return false;
}

// Verifica se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$isAdminPrincipal = isAdminPrincipal($user_id);
$user_level = getUserLevel($user_id);

// Verifica se o nível do usuário é 1
if ($user_level === 1) {
    header("Location: ../index.php");
    exit();
}


// Verifica se o utilizador está logado com o mesmo session id
if (isset($_SESSION['user_id'])) {
    // Obtém o ID do utilizador da sessão
    $user_id = $_SESSION['user_id'];

    // Obtém o PHP Session ID atual
    $current_session_id = session_id();

    // Consulta o banco de dados para verificar o session_id do utilizador
    $stmt = $conn->prepare("SELECT session_id FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $db_session_id = $stmt->fetchColumn();

    // Compara o session_id atual com o armazenado na base de dados
    if ($db_session_id !== $current_session_id) {
        // Encerra a sessão e exibe mensagem de erro
        session_destroy();
        die("Sessão encerrada: conta iniciada em outro dispositivo.");
    }
} else {
    // Se não estiver logado, redireciona para a página de login
    header("Location: login.php");
    exit();
}

// Logout do usuário
if (isset($_POST['logout'])) {
    // Destrói a sessão
    session_destroy();

    // Remove o cookie de autenticação
    setcookie('cookie', '', time() - 3600, '/');

    // Redireciona para a página de login
    header("Location: ../login.php");
    exit();
}

// Excluir automaticamente reservas antigas
deleteOldReservations();

// Excluir uma reserva
if (isset($_POST['remover_reserva'])) {
    $id_reserva = $_POST['id_reserva'];
    if (removeReservation($id_reserva, $user_id)) {
        echo "<script>alert('Reserva removida com sucesso!');</script>";
    } else {
        echo "<script>alert('Erro ao remover a reserva!');</script>";
    }
}
?>

<!DOCTYPE html>
<link rel="stylesheet" href="../css/style2.css">
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Reservas</title>
</head>
<body>

<h1>Gestão de Reservas</h1>

<!-- Exibe a mensagem de boas-vindas para o admin -->
<?php if ($isAdminPrincipal): ?>
    <p>Bem-vindo, Admin Principal!</p>
<?php else: ?>
    <p>Bem-vindo, Admin!</p>
<?php endif; ?>

<!-- Botão de Logout -->
<form action="" method="POST">
    <button type="submit" name="logout">Logout</button>
</form>

<!-- Botão para voltar para admin.php -->
<form action="admin.php" method="GET">
    <button type="submit">Voltar para Área Administrativa</button>
</form>

<hr>

<!-- Adicione um formulário de pesquisa -->
<form action="" method="GET">
    <label for="search_usuario">Pesquisar por Utilizador:</label>
    <input type="text" id="search_usuario" name="search_usuario" placeholder="Nome do Utilizador">

    <label for="search_sala">Pesquisar por Sala:</label>
    <input type="text" id="search_sala" name="search_sala" placeholder="Nome da Sala">

    <label for="search_data">Pesquisar por Data:</label>
    <input type="date" id="search_data" name="search_data">

    <button type="submit">Pesquisar</button>
</form>
<hr>

<!-- Tabela com as reservas -->
<h2>Reservas Existentes</h2>
<div class="table-container">
    <table border="1">
        <thead>
        <tr>
            <th>ID Reserva</th>
            <th>Usuário</th>
            <th>Sala</th>
            <th>Data</th>
            <th>Hora Início</th>
            <th>Hora Fim</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Obtém os valores de pesquisa
        $search_usuario = isset($_GET['search_usuario']) ? $_GET['search_usuario'] : '';
        $search_sala = isset($_GET['search_sala']) ? $_GET['search_sala'] : '';
        $search_data = isset($_GET['search_data']) ? $_GET['search_data'] : '';

        // Prepara a consulta com base nos filtros
        $query = "
            SELECT reservas.id, utilizadores.nome AS usuario, salas.nome AS sala, reservas.data_reserva, reservas.hora_inicio, reservas.hora_fim
            FROM reservas
            JOIN utilizadores ON reservas.user_id = utilizadores.numero
            JOIN salas ON reservas.sala_id = salas.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search_usuario)) {
            $query .= " AND utilizadores.nome LIKE ?";
            $params[] = '%' . $search_usuario . '%';
        }
        if (!empty($search_sala)) {
            $query .= " AND salas.nome LIKE ?";
            $params[] = '%' . $search_sala . '%';
        }
        if (!empty($search_data)) {
            $query .= " AND reservas.data_reserva = ?";
            $params[] = $search_data;
        }

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reservas = $stmt->fetchAll();

        // Exibe as reservas na tabela
        if (count($reservas) > 0) {
            foreach ($reservas as $reserva) {
                echo "<tr>
                        <td>{$reserva['id']}</td>
                        <td>{$reserva['usuario']}</td>
                        <td>{$reserva['sala']}</td>
                        <td>{$reserva['data_reserva']}</td>
                        <td>{$reserva['hora_inicio']}</td>
                        <td>{$reserva['hora_fim']}</td>
                        <td>
                            <form action='' method='POST' style='display:inline'>
                                <input type='hidden' name='id_reserva' value='{$reserva['id']}'>
                                <input type='submit' name='remover_reserva' value='Remover' class='btn'>
                            </form>
                        </td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='7'>Nenhuma reserva encontrada.</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>


</body>
</html>
