<?php
require '../../vendor/autoload.php'; // Inclui o autoload do Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclui a configuração do banco de dados
include '../config.php';
include '../verificar_bloqueio.php';
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

$mensagem = '';


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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));

    if ($nome) {
        try {
            // Verifica se o nome existe no banco de dados
            $stmt = $conn->prepare("SELECT email FROM utilizadores WHERE nome = ?");
            $stmt->execute([$nome]);
            $user = $stmt->fetch();

            if ($user) {
                $email = $user['email'];
                $token = bin2hex(random_bytes(32)); // Gera um token seguro

                // Salva o token no banco de dados
                $stmt = $conn->prepare("
                    UPDATE utilizadores 
                    SET reset_token = ?, token_expiration = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                    WHERE nome = ?
                ");
                $stmt->execute([$token, $nome]);

                if ($stmt->rowCount() > 0) {
                    // Prepara o link de redefinição de password
                    $resetLink = "https://saw.local/recuperar_password/reset_password.php?token=" . urlencode($token);

                    // Configura e envia o e-mail com o PHPMailer
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com'; // Servidor SMTP do Gmail
                        $mail->SMTPAuth = true;
                        $mail->Username = 'example@gmail.com'; // Seu e-mail
                        $mail->Password = 'passwordg  email example';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Configura o remetente e destinatário
                        $mail->setFrom('example@gmail.com', 'Saw.local');
                        $mail->addAddress($email, $nome);

                        // Conteúdo do e-mail
                        $mail->isHTML(true);
                        $mail->Subject = "Redefinicao de Password";
                        $mail->Body = "Ola $nome,<br><br>Clique no link abaixo para redefinir sua password:<br><br><a href='$resetLink'>$resetLink</a><br><br>Este link e valido por 1 hora.";
                        $mail->AltBody = "Ola $nome,\n\nClique no link abaixo para redefinir sua password:\n\n$resetLink\n\nEste link e valido por 1 hora.";

                        $mail->send();
                        registrarLog("Email de recuperação de password solicitado para email $email do user $nome .");
                        $mensagem = "Um e-mail com as instruções será enviado.";
                    } catch (Exception $e) {
                        error_log("Erro ao enviar e-mail: " . $mail->ErrorInfo);
                        $mensagem = "Erro ao enviar o e-mail. Por favor, tente novamente mais tarde.";
                    }
                }
            } else {
                registrarLog("Email de recuperação de password solicitado.");
                $mensagem = "Um e-mail com as instruções será enviado.";
            }
        } catch (PDOException $e) {
            error_log("Erro na recuperação de password: " . $e->getMessage());
            $mensagem = "Erro ao processar o pedido. Por favor, tente novamente.";
        }
    } else {
        $mensagem = "Por favor, insira um nome de utilizador válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<link rel="stylesheet" href="../css/style2.css">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Password</title>
</head>
<body>
<h1>Recuperação de Password</h1>

<?php if (!empty($mensagem)): ?>
    <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form action="" method="POST">
    <label for="nome">Nome do utilizador:</label>
    <input type="text" name="nome" id="nome" required>
    <button type="submit">Enviar</button>
</form>
</body>
</html>