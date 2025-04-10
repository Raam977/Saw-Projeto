<?php
// Inclui a configuração do banco de dados
include '../config.php';
include '../verificar_bloqueio.php';
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

// Inicializa variáveis
$mensagem = '';
$nome = '';
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);



function registrarLog($mensagem) {
    global $conn;
    $diretorioLogs = __DIR__ . '../../../logs';
    $arquivoLog = $diretorioLogs . '/perfil_alteracoes_log.txt';

    // Cria o diretório de logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }



    // Adiciona data e hora à mensagem de log
    $dataHora = date('Y-m-d H:i:s');
    $mensagemLog = "[$dataHora] $mensagem\n";

    // Escreve a mensagem no arquivo de log
    file_put_contents($arquivoLog, $mensagemLog, FILE_APPEND);
}

// Valida o token
if ($token) {
    try {
        $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE reset_token = ? AND token_expiration > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $nome = htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8');
        } else {
            $mensagem = "O link para redefinição de password é inválido ou expirou.";
        }
    } catch (PDOException $e) {
        error_log("Erro ao validar token: " . $e->getMessage());
        $mensagem = "Erro ao processar o pedido. Por favor, tente novamente.";
    }
} else {
    $mensagem = "Token não fornecido.";
}

// Processa o formulário de redefinição de password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $nome) {
    $password = $_POST['password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';

    // Validação das passwords
    if (empty($password) || empty($confirmarPassord)) {
        $mensagem = "Por favor, preencha todos os campos.";
    } elseif ($password !== $confirmarPassword) {
        $mensagem = "As passwords não coincidem.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $mensagem = "A password deve ter pelo menos 8 caracteres, incluindo uma letra maiúscula, um número e um caractere especial.";
    } else {
        try {
            // Atualiza a password no banco de dados
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE utilizadores SET password = ?, reset_token = NULL, token_expiration = NULL WHERE reset_token = ?");
            $stmt->execute([$passwordHash, $token]);
            registrarLog("A Password foi altereda por email de recuperação do user $nome.");
            if ($stmt->rowCount()) {
                $mensagem = "Sua password foi redefinida com sucesso!";
                $nome = ''; // Limpa o nome para esconder o formulário

            } else {
                $mensagem = "Erro ao redefinir a password. Por favor, tente novamente.";
            }
        } catch (PDOException $e) {
            error_log("Erro ao redefinir password: " . $e->getMessage());
            $mensagem = "Erro ao processar o pedido. Por favor, tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<link rel="stylesheet" href="../css/style2.css">
<head>
    <meta charset="UTF-8">
    <title>Redefinição de Password</title>
</head>
<body>
<h1>Redefinição de Password</h1>

<?php if (!empty($mensagem)): ?>
    <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($nome): ?>
    <p>Olá, <strong><?php echo $nome; ?></strong>. Por favor, insira sua nova password.</p>
    <form action="" method="POST">
        <label for="password">Nova password:</label><br>
        <input type="password" name="password" id="password" required minlength="8"><br><br>

        <label for="confirmar_password">Confirme sua nova password:</label><br>
        <input type="password" name="confirmar_password" id="confirmar_password" required minlength="8"><br><br>

        <button type="submit">Redefinir Password</button>
    </form>
<?php endif; ?>

</body>
</html>