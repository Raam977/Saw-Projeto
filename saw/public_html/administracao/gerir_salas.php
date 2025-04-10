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


// Adicionar uma nova sala
if (isset($_POST['adicionar'])) {
    $nome = $_POST['nome'];
    $estado = $_POST['estado'];
    $descricao = $_POST['descricao'] ?? null;

    $stmt = $conn->prepare("INSERT INTO salas (nome, estado, descricao) VALUES (?, ?, ?)");
    if ($stmt->execute([$nome, $estado, $descricao])) {
        echo "Sala adicionada com sucesso!";
    } else {
        echo "Erro ao adicionar a sala!";
    }
}

// Alterar uma sala existente
if (isset($_POST['alterar'])) {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $estado = $_POST['estado'];
    $descricao = $_POST['descricao'] ?? null;

    // Preparar a atualização
    if (!empty($nome) && !empty($estado)) {
        // Se ambos os campos foram preenchidos, atualiza nome, estado e descrição
        $stmt = $conn->prepare("UPDATE salas SET nome = ?, estado = ?, descricao = ? WHERE id = ?");
        $stmt->execute([$nome, $estado, $descricao, $id]);
        echo "Sala alterada com sucesso!";
    } elseif (!empty($estado)) {
        // Se apenas o estado foi preenchido
        $stmt = $conn->prepare("UPDATE salas SET estado = ?, descricao = ? WHERE id = ?");
        $stmt->execute([$estado, $descricao, $id]);
        echo "Estado da sala alterado com sucesso!";
    } elseif (!empty($descricao)) {
        // Se apenas a descrição foi preenchida
        $stmt = $conn->prepare("UPDATE salas SET descricao = ? WHERE id = ?");
        $stmt->execute([$descricao, $id]);
        echo "Descrição da sala alterada com sucesso!";
    } else {
        echo "Nenhuma alteração foi feita!";
    }
}

// Remover uma sala
if (isset($_POST['remover'])) {
    $id = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM salas WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo "Sala removida com sucesso!";
    } else {
        echo "Erro ao remover a sala!";
    }
}
?>

<!DOCTYPE html>
<link rel="stylesheet" href="../css/style2.css">
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Salas</title>
</head>
<div>

<h1>Gestão de Salas</h1>

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
    <button type="submit">Voltar para Area Administrativa</button>
</form>
    </div>

<hr>
<div class="table-container">
<!-- Tabela com as salas -->
<h2>Salas Disponíveis</h2>
<table border="1">
    <thead>
    <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Estado</th>
        <th>Descrição</th>
        <th>Ações</th>
    </tr>
    </thead>


    <tbody>
    <?php
    // Consulta para buscar todas as salas
    $stmt = $conn->prepare("SELECT * FROM salas");
    $stmt->execute();
    $salas = $stmt->fetchAll();


    // Exibe as salas na tabela
    foreach ($salas as $sala) {
        echo "
<tr>
                    <td>{$sala['id']}</td>
                    <td>{$sala['nome']}</td>
                    <td>{$sala['estado']}</td>
                    <td>" . htmlspecialchars($sala['descricao'] ?? '') . "</td>
                    <td>
                        <form action='gerir_salas.php' method='POST' style='display:inline'>
                            <input type='hidden' name='id' value='{$sala['id']}'>
                            <input type='submit' name='remover' value='Remover'>
                        </form>
                         <div>
                        <form action='gerir_salas.php' method='POST' style='display:inline'>
                            <input type='hidden' name='id' value='{$sala['id']}'>
                            <input type='text' name='nome' placeholder='Novo nome'>
                           
                            <select name='estado'>
                                <option value='disponível'>Disponível</option>
                                <option value='indisponível'>Indisponível</option>
                                <option value='brevemente'>Brevemente</option>
                            </select>
                            <textarea name='descricao' placeholder='Descrição (se indisponível)'></textarea>
                            </div>
                            <input type='submit' name='alterar' value='Alterar'>
                        </form>
                        
                    </td>
                </tr>";
    }
    ?>
    </tbody>
</table>

</div>







<div class="table-container">


<!-- Formulário para adicionar uma nova sala -->
<h2>Adicionar Nova Sala</h2>
<form action="gerir_salas.php" method="POST" style='display:inline'>
    <label for="nome">Nome da Sala:</label>
    <input type="text" name="nome" id="nome" required><br><br>

    <label for="estado">Estado da Sala:</label>
    <select name="estado" id="estado">
        <option value="disponível">Disponível</option>
        <option value="indisponível">Indisponível</option>
        <option value="brevemente">Brevemente</option>
    </select><br><br>

    <label for="descricao">Descrição (opcional):</label>
    <textarea name="descricao" id="descricao" placeholder="Descrição da sala (opcional)"></textarea><br><br>

    <input type="submit" name="adicionar" value="Adicionar Sala">
</form>
    <hr>
</div>

</body>
</html>
