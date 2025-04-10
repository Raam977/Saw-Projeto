<?php
require '../vendor/autoload.php'; // Inclui o autoload do Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclui a configuração do banco de dados
include 'config.php';
include 'verificar_bloqueio.php';

date_default_timezone_set('Europe/Lisbon');

require '../vendor/autoload.php';

include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/FixedBitNotation.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleAuthenticator.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleQrUrl.php';

if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

$mensagem = '';

ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
session_start();


function validarPassword($nova_password) {
    if (strlen($nova_password) < 8) {
        return "A password deve ter pelo menos 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $nova_password)) {
        return "A password deve conter pelo menos uma letra maiúscula.";
    }
    if (!preg_match('/[\W_]/', $nova_password)) {
        return "A password deve conter pelo menos um caractere especial (ex.: @, #, $, etc.).";
    }
    return true; // password válida
}



$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

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
} else {
    // Se não estiver logado, redireciona para a página de login
    header("Location: login.php");
    exit();
}


// Função para pegar os dados do usuário
function getUserData($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT nome, email, password, foto_perfil, mfa FROM utilizadores WHERE numero = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Função para enviar o código de verificação por email
function sendVerificationCode($email) {
    $code = rand(100000, 999999); // Gera um código aleatório

    // Armazena o código e a data de expiração no banco de dados (1 hora de validade)
    global $conn;
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour')); // Define a validade para 1 hora
    $stmt = $conn->prepare("UPDATE utilizadores SET codigo_verificacao = ?, codigo_expiracao = ? WHERE email = ?");
    $stmt->execute([$code, $expiration, $email]);

    // Configura e envia o e-mail com o PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'example@gmail.com'; // Seu e-mail
        $mail->Password = 'email password example';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configura o remetente e destinatário
        $mail->setFrom('example@gmail.com', 'Saw.local');
        $mail->addAddress($email);

        // Conteúdo do e-mail
        $mail->isHTML(true);
        $mail->Subject = "Código de Verificação";
        $mail->Body = "O seu código de verificação é: $code";
        $mail->AltBody = "O seu código de verificação é: $code";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail: " . $mail->ErrorInfo);
        return false;
    }
}


// Função para ativar a MFA (Google Authenticator)
function ativarMFA($user_id) {
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();

    // Geração do segredo MFA
    $secret = $g->generateSecret();  // Gera o segredo MFA

    // Armazena o segredo gerado no banco de dados
    $mfaSecret = $secret;  // Atribui o segredo gerado à variável $mfaSecret
    // Usuário ou identificador para o QR Code
    $username = $user_id; // Isso pode ser o nome de usuário do seu sistema

    // Geração da URL do QR Code
    $qrCodeUrl = \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($username, $secret, 'Gestão de salas');



    // Atualiza o banco de dados com o segredo da MFA
    global $conn;
    $stmt = $conn->prepare("UPDATE utilizadores SET mfa = 1, mfa_secret = ? WHERE numero = ?");
    $stmt->execute([$secret, $user_id]);

    return $qrCodeUrl;
}

function desativarMFA($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE utilizadores SET mfa = 0, mfa_secret = NULL WHERE numero = ?");
    $stmt->execute([$user_id]);
}

// Obtém os dados do usuário
$user_data = getUserData($user_id);
$nome = $user_data['nome'];
$email = $user_data['email'];
$password_hash = $user_data['password']; // Password hash do usuário
$foto_perfil = $user_data['foto_perfil'];
$mfa = $user_data['mfa']; // Verifica se a MFA está ativa



// Função para registrar logs de alterações no perfil
function registrarLog($mensagem) {
    global $conn;
    $diretorioLogs = __DIR__ . '/../logs';
    $arquivoLog = $diretorioLogs . '/perfil_alteracoes_log.txt';

    // Cria o diretório de logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    // Recupera o nome do usuário
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $userName = $user['nome'] ?? 'Usuário desconhecido';
    } else {
        $userName = 'Usuário desconhecido';
    }

    // Adiciona data e hora à mensagem de log
    $dataHora = date('Y-m-d H:i:s');
    $mensagemLog = "[$dataHora] o Utilizador $userName com o id : $user_id: $mensagem\n";

    // Escreve a mensagem no arquivo de log
    file_put_contents($arquivoLog, $mensagemLog, FILE_APPEND);
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nome'])) {
        $novo_nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING));
        if ($novo_nome) {
            $stmt = $conn->prepare("UPDATE utilizadores SET nome = ? WHERE numero = ?");
            $stmt->execute([$novo_nome, $user_id]);
            $nome_antigo = $nome;
            $nome = $novo_nome;
            $mensagem = "Nome atualizado com sucesso!";
            // Registro no log
            registrarLog("Alteração do nome '$nome_antigo' para '$novo_nome'.");
        }
    }

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto_perfil']['tmp_name'];
        $file_name = basename($_FILES['foto_perfil']['name']);
        $file_path = "./uploads/" . $file_name;

        // Move o arquivo para o diretório de uploads
        if (move_uploaded_file($file_tmp, $file_path)) {
            // Atualiza o caminho da foto no banco de dados
            $stmt = $conn->prepare("UPDATE utilizadores SET foto_perfil = ? WHERE numero = ?");
            $stmt->execute([$file_path, $user_id]);
            $foto_perfil = $file_path;
            $mensagem = "Foto de perfil atualizada com sucesso!";

            // Registro no log
            registrarLog("Foto de perfil atualizada.");
        } else {
            $mensagem = "Erro ao fazer o upload da foto. Tente novamente.";
        }
    }

    if (isset($_POST['password_antiga']) && isset($_POST['nova_password'])) {
        // Verifica a password antiga antes de enviar o código de verificação
        $password_antiga = $_POST['password_antiga'];
        if (password_verify($password_antiga, $password_hash)) {
            // Password antiga correta, envia o código de verificação
            if (sendVerificationCode($email)) {
                $mensagem = "Um código de verificação foi enviado para seu email. Por favor, insira o código para continuar a alteração da Password.";
            } else {
                $mensagem = "Erro ao enviar o código de verificação. Tente novamente.";
            }
        } else {
            $mensagem = "A Password antiga está incorreta. Tente novamente.";
        }
    }

    if (isset($_POST['codigo_verificacao']) && isset($_POST['password_nova'])) {
        // Verifica o código de verificação e atualiza a password
        $codigo_digitado = $_POST['codigo_verificacao'];
        $nova_password = $_POST['password_nova'];
        $passwordValida = validarPassword($nova_password);

        if ($passwordValida !== true) {
            $errors['password'] = $passwordValida;
        }

        // Verifica o código e se ele não expirou
        $stmt = $conn->prepare("SELECT codigo_verificacao, codigo_expiracao FROM utilizadores WHERE numero = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if ($row) {
            $codigo_salvo = $row['codigo_verificacao'];
            $codigo_expiracao = $row['codigo_expiracao'];

            // Verifica se o código corresponde e não expirou
            if ($codigo_digitado == $codigo_salvo && strtotime($codigo_expiracao) > time()) {
                // Atualiza a password no banco de dados
                $password_hash = password_hash($nova_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE utilizadores SET password = ?, codigo_verificacao = NULL, codigo_expiracao = NULL WHERE numero = ?");
                $stmt->execute([$password_hash, $user_id]);

                $mensagem = "Password atualizada com sucesso!";

                // Registro no log
                registrarLog("Solicitação de alteração de password.");
            } else {
                $mensagem = "Código de verificação inválido ou expirado.";
            }
        }


    }

    // Processamento do formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['ativar_mfa'])) {
            // Ativa a MFA se não estiver ativada
            if ($mfa == 0) {
                $qrCodeUrl = ativarMFA($user_id);
                registrarLog("mfa ativado.");
                $mensagem = "A MFA foi ativada com sucesso! Escaneie o QR Code abaixo com o Google Authenticator.";
            }
        }

        if (isset($_POST['desativar_mfa'])) {
            // Desativa a MFA se estiver ativada
            if ($mfa == 1) {
                desativarMFA($user_id);
                registrarLog("mfa desativado.");
                $mensagem = "A MFA foi desativada com sucesso!";
            }
        }



    }
}





?>

<!DOCTYPE html>
<html lang="pt-PT">
<link rel="stylesheet" href="../css/style2.css">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil</title>
    <form action="index.php" method="GET">
        <button type="submit">Voltar</button>
    </form>
</head>
<body>
<h1>Editar Perfil</h1>

<?php if (!empty($mensagem)): ?>
    <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">
    <label for="nome">Nome:</label>
    <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" required>
    <br>
    <label for="foto_perfil">Foto de Perfil:</label>
    <input type="file" name="foto_perfil" id="foto_perfil">
    <br>
    <?php if ($foto_perfil): ?>
        <img src="<?php echo htmlspecialchars($foto_perfil, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de Perfil" width="100">
    <?php endif; ?>
    <button type="submit">Salvar Alterações</button>
</form>

<hr>

<h3>Alterar Password</h3>
<form action="" method="POST">
    <label for="password_antiga">Password Antiga:</label>
    <input type="password" name="password_antiga" id="password_antiga" required>

    <label for="nova_password">Nova Password:</label>
    <input type="password" name="nova_password" id="nova_password" required>
    <button type="submit">Enviar Código de Verificação</button>
</form>

<hr>

<?php if (isset($_POST['nova_password']) && !empty($mensagem)): ?>
    <h3>Alterar Password - Passo 2</h3>
    <form action="" method="POST">
        <label for="codigo_verificacao">Código de Verificação:</label>
        <input type="text" name="codigo_verificacao" id="codigo_verificacao" required>
        <label for="password_nova">Nova Password:</label>
        <input type="password" name="password_nova" id="password_nova" required>
        <button type="submit">Alterar Password</button>
    </form>
<?php endif; ?>


<?php if ($mfa == 0): ?>
    <h3>Ativar MFA</h3>
    <form action="" method="POST">
        <button type="submit" name="ativar_mfa">Ativar MFA</button>
    </form>
    <?php if (isset($qrCodeUrl)): ?>
        <p>Escaneie o QR Code abaixo com o Google Authenticator:</p>
        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code de MFA">
    <?php endif; ?>
<?php else: ?>
<h3>Desativar MFA</h3>
    <form action="" method="POST">
        <button type="submit" name="desativar_mfa">Desativar MFA</button>
    </form>
<?php endif; ?>
</body>
</html>
