<?php
// Inclui o arquivo de configuração para conectar ao banco de dados
include 'config.php';

if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

// Funções de validação
function validarPrimeiroNome($nome) {
    return preg_match("/^[a-zA-ZÀ-ÿ\s]{3,}$/", $nome);
}

function validarNivel($nivel) {
    return is_numeric($nivel) && $nivel >= 1 && $nivel <= 5; // Exemplo: níveis entre 1 e 5
}

$errors = [];

// Inserir utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inserir'])) {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    if (!validarPrimeiroNome($nome)) {
        $errors['nome'] = "O nome deve ter pelo menos 3 caracteres e conter apenas letras.";
    }

    $login = htmlspecialchars($_POST['login'], ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];
    $nivel = (int)$_POST['nivel'];
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "E-mail inválido!";
    }
    if (!validarNivel($nivel)) {
        $errors['nivel'] = "Nível inválido! Deve ser um número entre 1 e 5.";
    }

    if (empty($errors)) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO utilizadores (nome, login, password, nivel, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $login, $passwordHash, $nivel, $email]);

            echo "Utilizador inserido com sucesso!";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors['database'] = "Erro ao inserir utilizador. Por favor, tente novamente.";
        }
    }
}

// Deletar utilizador
if (isset($_GET['delete'])) {
    $numero = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM utilizadores WHERE numero = ?");
        $stmt->execute([$numero]);
        echo "Utilizador deletado com sucesso!";
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo "Erro ao deletar utilizador.";
    }
}

// Atualizar utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar'])) {
    $numero = (int)$_POST['numero'];
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $login = htmlspecialchars($_POST['login'], ENT_QUOTES, 'UTF-8');
    $nivel = (int)$_POST['nivel'];
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!validarPrimeiroNome($nome)) {
        $errors['nome'] = "O nome deve ter pelo menos 3 caracteres e conter apenas letras.";
    }
    if (!validarNivel($nivel)) {
        $errors['nivel'] = "Nível inválido!";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "E-mail inválido!";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE utilizadores SET nome = ?, login = ?, nivel = ?, email = ? WHERE numero = ?");
            $stmt->execute([$nome, $login, $nivel, $email, $numero]);
            echo "Utilizador atualizado com sucesso!";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Erro ao atualizar utilizador.";
        }
    }
}

// Consulta de utilizadores
$utilizadores = $conn->query("SELECT * FROM utilizadores")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciamento de Utilizadores</title>
</head>
<body>
<h1>Inserir Novo Utilizador</h1>
<form action="" method="POST">
    Nome: <input type="text" name="nome" required><br>
    <?php if (isset($errors['nome'])) echo "<p class='error'>{$errors['nome']}</p>"; ?>
    Login: <input type="text" name="login" required><br>
    Senha: <input type="password" name="password" required><br>
    Nível: <input type="number" name="nivel" required><br>
    Email: <input type="email" name="email" required><br>
    <?php if (isset($errors['email'])) echo "<p class='error'>{$errors['email']}</p>"; ?>
    <button type="submit" name="inserir">Inserir</button>
</form>

<h1>Utilizadores</h1>
<table border="1">
    <tr>
        <th>Número</th>
        <th>Nome</th>
        <th>Login</th>
        <th>Nível</th>
        <th>Email</th>
        <th>Ações</th>
    </tr>
    <?php foreach ($utilizadores as $user): ?>
        <tr>
            <td><?php echo $user['numero']; ?></td>
            <td><?php echo $user['nome']; ?></td>
            <td><?php echo $user['login']; ?></td>
            <td><?php echo $user['nivel']; ?></td>
            <td><?php echo $user['email']; ?></td>
            <td>
                <a href="?edit=<?php echo $user['numero']; ?>">Editar</a> |
                <a href="?delete=<?php echo $user['numero']; ?>" onclick="return confirm('Tem certeza que deseja deletar?')">Deletar</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (isset($_GET['edit'])):
    $numero = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero = ?");
    $stmt->execute([$numero]);
    $editUser = $stmt->fetch();
    ?>
    <h1>Editar Utilizador</h1>
    <form action="" method="POST">
        <input type="hidden" name="numero" value="<?php echo $editUser['numero']; ?>">
        Nome: <input type="text" name="nome" value="<?php echo $editUser['nome']; ?>" required><br>
        Login: <input type="text" name="login" value="<?php echo $editUser['login']; ?>" required><br>
        Nível: <input type="number" name="nivel" value="<?php echo $editUser['nivel']; ?>" required><br>
        Email: <input type="email" name="email" value="<?php echo $editUser['email']; ?>" required><br>
        <button type="submit" name="atualizar">Atualizar</button>
    </form>
<?php endif; ?>
</body>
</html>
