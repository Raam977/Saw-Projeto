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
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração</title>
    <link href="'https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/administracao.css">
</head>
<body>

<div class="blur-background"></div>

<header>
    <h1>Painel de Administração</h1>
    <p>Bem-vindo ao sistema de gestão!</p>
</header>

<div class="container">
    <div class="welcome-message">
        <!-- Exibe a mensagem de boas-vindas para o admin -->
        <?php if ($isAdminPrincipal): ?>
            <p>Bem-vindo, Admin Principal!</p>
        <?php else: ?>
            <p>Bem-vindo, Admin!</p>
        <?php endif; ?>
    </div>

    <!-- Formulário de Logout e Outros Botões -->
    <form action="" method="POST">
        <!-- Logout -->
        <button type="submit" name="logout" class="logout-btn">Logout</button>
    </form>

    <form action="gerir_users.php" method="GET">
        <button type="submit">Gerir Utilizadores</button>
    </form>

    <form action="gerir_salas.php" method="GET">
        <button type="submit">Gerir Salas</button>
    </form>

    <form action="gerir_reservas.php" method="GET">
        <button type="submit">Gerir Reservas</button>
    </form>

    <form action="../index.php" method="GET">
        <button type="submit">Requisitar</button>
    </form>

    <form action="../alterar_perfil.php" method="GET">
        <button type="submit">Alterar Perfil</button>
    </form>
</div>

<footer>
    <p>&copy; 2024 Gestão de Salas. Todos os direitos reservados.</p>
</footer>

</body>
</html>
