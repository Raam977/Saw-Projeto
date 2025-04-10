<?php
// Inclui o arquivo de configuração para conectar ao banco de dados
include '../config.php';
include '../verificar_bloqueio.php';
// Verifica se a variável $conn foi definida
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

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
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
// Inicia a sessão
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}




$user_id = $_SESSION['user_id'];
$isAdminPrincipal = isAdminPrincipal($user_id);
$user_level = getUserLevel($user_id);



function getUserName($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? $user['nome'] : null;
}
$user_admin = getUserName($user_id);

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


function logAction($message) {
    $diretorioLogs = __DIR__ . '/../../logs';
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    $arquivoLog = $diretorioLogs . '/gerir_users.log';
    $dataHora = date('Y-m-d H:i:s');
    $logMessage = "[$dataHora] $message\n";
    file_put_contents($arquivoLog, $logMessage, FILE_APPEND);
}


// Função para deletar um utilizador
if (isset($_GET['delete'])) {
    $numero = (int)$_GET['delete'];

    // Não permite deletar o admin principal
    if ($numero != $user_id && !isAdminPrincipal($numero)) {
        // Obter o nome do utilizador para o log
        $nome_user_stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
        $nome_user_stmt->execute([$numero]);
        $nome_user = $nome_user_stmt->fetchColumn(); // Obtém apenas o valor da coluna 'nome'

        try {
            // Iniciar uma transação
            $conn->beginTransaction();

            // Deletar todas as reservas do utilizador
            $stmt_reservas = $conn->prepare("DELETE FROM reservas WHERE user_id = ?");
            $stmt_reservas->execute([$numero]);

            // Deletar o utilizador
            $stmt_user = $conn->prepare("DELETE FROM utilizadores WHERE numero = ?");
            $stmt_user->execute([$numero]);

            // Confirmar a transação
            $conn->commit();

            echo "Usuário e suas reservas foram deletados com sucesso!";
            logAction("Usuário '$nome_user' com o ID $numero e todas suas reservas foram deletados pelo admin com ID $user_id.");
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $conn->rollBack();
            echo "Erro ao deletar o utilizador e suas reservas: " . $e->getMessage();
        }
    } else {
        echo "Não é permitido remover o admin principal!";
    }
}

// Função para alternar permissões de admin
if (isset($_GET['toggle_admin'])) {
    $numero = (int)$_GET['toggle_admin'];

    // Não permite alterar permissões do admin principal
    if ($numero != $user_id && !isAdminPrincipal($numero)) {
        $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero = ?");
        // Obter o nome do utilizador para o log
        $nome_user_stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
        $nome_user_stmt->execute([$numero]);
        $nome_user = $nome_user_stmt->fetchColumn(); // Obtém apenas o valor da coluna 'nome'
        $stmt->execute([$numero]);
        $user = $stmt->fetch();

        if ($user) {
            $novo_nivel = ($user['nivel'] == 2) ? 1 : 2; // Alterna entre admin (2) e usuário comum (1)
            $stmt = $conn->prepare("UPDATE utilizadores SET nivel = ? WHERE numero = ?");
            $stmt->execute([$novo_nivel, $numero]);
            $acao = ($novo_nivel == 2) ? 'promovido a admin' : 'rebaixado para usuário comum';
            logAction("Usuário $nome_user com id $numero foi $acao pelo admin $user_admin com id $user_id.");
            echo "Permissão de admin alterada com sucesso!";
        }
    } else {
        echo "Não é permitido remover a permissão de admin do admin principal!";
    }
}
// Captura o parâmetro de busca
$nome_busca = isset($_GET['nome_busca']) ? trim($_GET['nome_busca']) : '';

// Consulta para exibir todos os usuários, com ou sem filtro
if ($nome_busca !== '') {
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero != ? AND nome LIKE ?");
    $stmt->execute([$user_id, '%' . $nome_busca . '%']);
} else {
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero != ?");
    $stmt->execute([$user_id]);
}
$utilizadores = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html lang="pt-BR">
<link rel="stylesheet" href="../css/style.css">
<head>
    <meta charset="UTF-8">
    <title>Administração de Utilizadores</title>
</head>
<body>
<h1>Gerenciamento de Utilizadores</h1>

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
    <button type="submit">Voltar para Area Administrativa</button>
</form>

<!-- Botão para Gerir Salas -->
<form action="gerir_salas.php" method="GET">
    <button type="submit">Gerir Salas</button>
</form>

<!-- Formulário de busca -->
<h2>Buscar Utilizadores</h2>
<form action="" method="GET">
    <input type="text" name="nome_busca" placeholder="Digite o nome do utilizador" value="<?php echo htmlspecialchars($nome_busca); ?>">
    <button type="submit">Buscar</button>
</form>

<h2>Usuários</h2>
<table border="1">
    <tr>
        <th>Número</th>
        <th>Nome</th>
        <th>Login</th>
        <th>Nível</th>
        <th>Email</th>
        <th>Ações</th>
    </tr>
    <?php if (count($utilizadores) > 0): ?>
        <?php foreach ($utilizadores as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['numero']); ?></td>
                <td><?php echo htmlspecialchars($user['nome']); ?></td>
                <td><?php echo htmlspecialchars($user['login']); ?></td>
                <td><?php echo htmlspecialchars($user['nivel']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <!-- Ações somente para usuários que não são admin principal -->
                    <?php if (!isAdminPrincipal($user['numero'])): ?>
                        <a href="?delete=<?php echo $user['numero']; ?>" onclick="return confirm('Tem certeza que deseja deletar?')">Deletar</a> |
                        <a href="?toggle_admin=<?php echo $user['numero']; ?>">Alterar Permissão de Admin</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">Nenhum utilizador encontrado.</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>
