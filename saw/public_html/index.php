<?php
// Inclui o arquivo de configuração para a conexão com o banco de dados
include 'config.php';
include 'verificar_bloqueio.php';
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

date_default_timezone_set('Europe/Lisbon');


ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
// Inicia a sessão
session_start();

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;




// Verifica se o utilizador está logado
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
}

// Verifica se o usuário logado é o admin principal
function isAdminPrincipal($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE (login = ? OR nome = ?) AND numero = ?");
    $stmt->execute(['admin', 'admin', $user_id]);
    return $stmt->fetch() !== false;
}

// Verifica se o usuário tem permissão de admin (nível 2)
function hasAdminPermission($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero = ? AND nivel = 2");
    $stmt->execute([$user_id]);
    return $stmt->fetch() !== false;
}

// Função para buscar a foto do perfil

function getUserProfilePicture($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT foto_perfil FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? $user['foto_perfil'] : null;
}

// Função para pegar o nome do usuário
function getUserName($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? $user['nome'] : null; // Retorna o nome do usuário ou null se não encontrar
}



$isAdminPrincipal = $user_id ? isAdminPrincipal($user_id) : false;
$hasAdminPermission = $user_id ? hasAdminPermission($user_id) : false;

// Botão de logout
if (isset($_POST['logout'])) {
    session_destroy();
    setcookie('cookie', '', time() - 3600, '/');
    header("Location: login.php");
    exit();
}


// Remover automaticamente reservas com datas passadas
$currentDateTime = date('Y-m-d H:i:s');

// Remove reservas com data/hora passada
$stmt = $conn->prepare("
    DELETE FROM reservas 
    WHERE user_id = ? 
    AND data_reserva < ?
");
$stmt->execute([$user_id, $currentDateTime]);

$diretorioLogs = __DIR__ . '/../logs';
$arquivoLog = $diretorioLogs . '/remocoes_reservas.log';

// Cria o diretório de logs se não existir
if (!file_exists($diretorioLogs)) {
    mkdir($diretorioLogs, 0777, true);
}
$user_name = getUserName($user_id);
// Registra o log de remoção
$dataHora = date('Y-m-d H:i:s');
$logMessage = "[$dataHora]  Remoção de requisições antigas automaticas do User: $user_name  com (id $user_id) \n";
file_put_contents($arquivoLog, $logMessage, FILE_APPEND);



?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Gestão de Salas</title>
</head>
<body>
<div class="blur-background"></div>
<h1>Gestão de Salas</h1>

<?php if ($user_id): ?>





    <!-- USUÁRIO LOGADO -->
    <div class="user-header">
        <?php
        // Exibir foto do perfil, se existir
        $fotoPerfil = getUserProfilePicture($user_id);
        if ($fotoPerfil): ?>
            <img src="<?php echo ltrim($fotoPerfil, '/'); ?>" alt="Foto de Perfil" class="user-profile-pic">
        <?php else: ?>
            <!-- Imagem padrão caso o usuário não tenha foto de perfil -->
            <img src="uploads/default_profile.jpg" alt="Foto de Perfil Padrão" class="user-profile-pic">
        <?php endif; ?>



        <?php if ($isAdminPrincipal): ?>
            <p>Bem-vindo, <?php echo htmlspecialchars(getUserName($user_id)); ?>. Está logado como Admin Principal!</p>
        <?php elseif ($hasAdminPermission): ?>
            <p>Bem-vindo,<?php echo htmlspecialchars(getUserName($user_id)); ?>. Está logado como Admin!</p>
        <?php else: ?>
            <p>Bem-vindo, <?php echo htmlspecialchars(getUserName($user_id)); ?>!</p>
        <?php endif; ?>

        <!-- Botão de Logout -->
        <form action="" method="POST" class="logout-form">
            <button class="btn" type="submit" name="logout">Logout</button>
        </form>

        <!-- Botão de Alterar Perfil -->
        <form action="alterar_perfil.php" method="GET">
            <button class="btn" type="submit">Alterar Perfil</button>
        </form>


        <!-- Botão para admin -->
        <?php if ($isAdminPrincipal || $hasAdminPermission): ?>
            <a href="./administracao/admin.php"><button class="btn">Acessar Área Administrativa</button></a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- VISITANTE NÃO LOGADO -->
    <p>Bem-vindo, Visitante! Faça login para acessar mais funcionalidades.</p>
    <a href="login.php"><button class="btn">Login</button></a>
<?php endif; ?>

<!-- Tabela com todas as salas -->
<h2>Salas</h2>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Nome</th>
    </tr>
    </thead>
    <tbody>
    <?php
    // Busca todas as salas no banco de dados
    $stmt = $conn->prepare("SELECT id, nome, estado FROM salas");
    $stmt->execute();
    $salas = $stmt->fetchAll();

    foreach ($salas as $sala) {
        echo "<tr>
                <td>{$sala['id']}</td>
                <td>{$sala['nome']}</td>
              </tr>";
    }
    ?>
    </tbody>
</table>

<?php if ($user_id): ?>
    <!-- Exibir Salas Disponíveis -->
    <h2>Salas Disponíveis</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Busca as salas disponíveis
        $stmt = $conn->prepare("SELECT * FROM salas WHERE estado = 'disponível'");
        $stmt->execute();
        $salasDisponiveis = $stmt->fetchAll();

        foreach ($salasDisponiveis as $sala) {
            echo "<tr>
                    <td>{$sala['id']}</td>
                    <td>{$sala['nome']}</td>
                    <td>{$sala['estado']}</td>
                  </tr>";
        }
        ?>
        </tbody>
    </table>

    <!-- Botão para requisitar sala -->
    <a href="requesitar_sala.php"><button class="btn">Requisitar Sala</button></a>

    <!-- Exibir Salas Indisponíveis -->
    <h2>Salas Indisponíveis</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $conn->prepare("SELECT id, nome, descricao, estado FROM salas WHERE estado IN ('indisponível', 'brevemente')");
        $stmt->execute();
        $salasIndisponiveis = $stmt->fetchAll();

        if (count($salasIndisponiveis) > 0):
            foreach ($salasIndisponiveis as $sala):
                echo "<tr>
                        <td>{$sala['id']}</td>
                        <td>{$sala['nome']}</td>
                        <td>" . htmlspecialchars($sala['descricao']) . "</td>
                        <td>{$sala['estado']}</td>
                      </tr>";
            endforeach;
        else:
            echo "<tr><td colspan='4'>Nenhuma sala indisponível no momento.</td></tr>";
        endif;
        ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Exibir Reservas do Usuário -->
<?php if ($user_id): ?>
    <h2>Minhas Reservas</h2>
    <table>
        <thead>
        <tr>
            <th>ID Reserva</th>
            <th>Sala</th>
            <th>Data da Reserva</th>
            <th>Hora Início</th>
            <th>Hora Fim</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Busca as reservas do usuário logado
        $stmt = $conn->prepare("
            SELECT reservas.id, salas.nome AS sala_nome, reservas.data_reserva, reservas.hora_inicio, reservas.hora_fim
            FROM reservas
            JOIN salas ON reservas.sala_id = salas.id
            WHERE reservas.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $minhasReservas = $stmt->fetchAll();

        if (count($minhasReservas) > 0):
            foreach ($minhasReservas as $reserva):
                echo "<tr>
                        <td>{$reserva['id']}</td>
                        <td>{$reserva['sala_nome']}</td>
                        <td>{$reserva['data_reserva']}</td>
                        <td>{$reserva['hora_inicio']}</td>
                        <td>{$reserva['hora_fim']}</td>
                        <td>
                            <form action='' method='POST' style='display:inline;'>
                                <input type='hidden' name='reserva_id' value='{$reserva['id']}'>
                                <button type='submit' name='remover_reserva' onclick='return confirm(\"Tem certeza que deseja remover esta reserva?\")'>Remover</button>
                            </form>
                        </td>
                      </tr>";
            endforeach;
        else:
            echo "<tr><td colspan='6'>Você ainda não fez nenhuma reserva.</td></tr>";
        endif;
        ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// Inicia o buffer de saída para evitar erros de "headers already sent"
ob_start();

// Inicializa uma variável para armazenar a mensagem de erro
$error_message = '';

// Verifica se o formulário de remoção foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_reserva'])) {
    $reserva_id = (int)$_POST['reserva_id'];

    $diretorioLogs = __DIR__ . '/../logs';
    $arquivoLog = $diretorioLogs . '/remocoes_reservas.log';

    // Cria o diretório de logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    $dataHora = date('Y-m-d H:i:s');
    // Verifica se a reserva pertence ao usuário logado
    $stmt = $conn->prepare("SELECT * FROM reservas WHERE id = ? AND user_id = ?");
    $stmt->execute([$reserva_id, $user_id]);
    $reserva = $stmt->fetch();

    if ($reserva) {
        // Remove a reserva
        $stmt = $conn->prepare("DELETE FROM reservas WHERE id = ?");
        $stmt->execute([$reserva_id]);

        // Registra o log de remoção
        $user_name = getUserName($user_id);
        $logMessage = "[$dataHora] O Usuário {$user_name} (ID: {$user_id}) removeu a reserva com ID {$reserva_id}.\n";
        file_put_contents($arquivoLog, $logMessage, FILE_APPEND);


        // Redireciona para a página para evitar reenvio de POST
        header("Location: " . $_SERVER['PHP_SELF']);
        exit(); // A execução do script é parada aqui

    }

}

// Finaliza o buffer de saída e envia o conteúdo para o navegador
ob_end_flush();
?>



</body>
</html>
