<?php
// Inclui o arquivo de configuração para conectar ao banco de dados
include 'config.php';
include 'verificar_bloqueio.php';
require '../vendor/autoload.php'; // Inclui o autoload do Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Verifica se a variável $conn foi definida
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}
date_default_timezone_set('Europe/Lisbon');


function bloquearIP($tempoBloqueio) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $ipPublico = file_get_contents('https://api.ipify.org') ?: 'IP público desconhecido';

    // Verifique se o IP já está bloqueado
    $stmt = $conn->prepare("SELECT * FROM bloqueios WHERE ip = ? AND desbloqueio_em > NOW()");
    $stmt->execute([$ipPublico]);
    $bloqueio = $stmt->fetch();

    $tempoRestante = $_SESSION['bloqueio_tempo'] - time();
    $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconhecido';
    $macAddress = 'MAC desconhecido'; // Valor padrão caso não seja possível capturar
    $hora = date('Y-m-d H:i:s');


    // Tenta capturar o MAC address (funciona em ambientes Windows/Linux controlados)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Para Windows
        $macAddress = exec('getmac') ?: 'MAC desconhecido';
    } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'LIN') {
        // Para Linux
        $macAddress = shell_exec("ip link show | awk '/ether/ {print $2}' | head -n 1") ?: 'MAC desconhecido';
    }
    $detalhesBloqueio = "[$hora]  IP Público: $ipPublico Bloqueado  globalmente , IP local:$ip MAC: $macAddress, Browser: $browser, Tempo Restante: $tempoRestante segundos. ";

    registrarBloqueio("Tentativas excessivas de login.");
    enviarEmailBloqueio($detalhesBloqueio);

    if (!$bloqueio) {
        // Registra o IP e o MAC address no banco de dados com o horário de desbloqueio
        $stmt = $conn->prepare("INSERT INTO bloqueios (ip, mac, desbloqueio_em) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))");
        $stmt->execute([$ipPublico, $macAddress, $tempoBloqueio]);
    }
}




// Função para enviar e-mail com detalhes de bloqueio
function enviarEmailBloqueio($detalhesBloqueio) {


    global $conn;
    $mail = new PHPMailer(true);

    try {
        // Configuração do servidor de e-mail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Servidor SMTP do Gmail
        $mail->SMTPAuth = true;
        $mail->Username = 'example@gmail.com'; // Seu e-mail
        $mail->Password = 'passowrd example';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configura o remetente e destinatário
        $mail->setFrom('example@gmail.com', 'Saw.local');




        // Busca e-mails dos administradores
        $stmt = $conn->prepare("SELECT email FROM utilizadores WHERE nivel = 2");
        $stmt->execute();
        $emailsAdmins = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Adiciona os destinatários
        foreach ($emailsAdmins as $email) {
            $mail->addAddress($email);
        }

        // Configuração do e-mail
        $mail->isHTML(true);
        $mail->Subject = 'Alerta: Bloqueio por Tentativas Excessivas';
        $mail->Body = "
            <h3>Detalhes do Bloqueio:</h3>
            <p>$detalhesBloqueio</p>
            <h2>Por favor, verifique o sistema.</h2>
        ";
        $mail->AltBody = strip_tags($mail->Body);

        // Envia o e-mail
        $mail->send();
        registrarBloqueio("E-mail de bloqueio enviado com sucesso.");
    } catch (Exception $e) {
        registrarBloqueio("Erro ao enviar e-mail: {$mail->ErrorInfo}");
    }
}


// Função para registrar logs
function registrarLog($mensagem) {

    // Diretório e arquivo de log

    $diretorioLogs = __DIR__ . '/../logs';
    $arquivoLog = $diretorioLogs . '/logins_log.txt';

    // Cria o diretório logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }
    $hora = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP desconhecido';
    $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconhecido';


    // Obtém IP público e MAC address (simulação - IP público geralmente não pode ser obtido diretamente em PHP)
    $ipPublico = file_get_contents('https://api.ipify.org') ?: 'IP público desconhecido';
    $macAddress = exec('getmac') ?: 'MAC desconhecido';

    $log = "[$hora] IP: $ip, IP Publico: $ipPublico, MAC: $macAddress, Browser: $browser, $mensagem" . PHP_EOL;

    file_put_contents($arquivoLog, $log, FILE_APPEND);


}

// Função para registrar logs de bloqueios com detalhes adicionais
function registrarBloqueio($mensagem) {

    // Diretório e arquivo de log
    $diretorioLogs = __DIR__ . '/../logs';
    $arquivoLog = $diretorioLogs . '/Bloqueios_log.txt';

    // Cria o diretório logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    $hora = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP desconhecido';
    $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconhecido';

    // Obtém IP público e MAC address (simulação - IP público geralmente não pode ser obtido diretamente em PHP)
    $ipPublico = file_get_contents('https://api.ipify.org') ?: 'IP público desconhecido';
    $macAddress = exec('getmac') ?: 'MAC desconhecido';

    $log = "[$hora] BLOQUEIO -> IP: $ip, IP Público: $ipPublico, MAC: $macAddress, Browser: $browser, $mensagem" . PHP_EOL;

    file_put_contents($arquivoLog, $log, FILE_APPEND);
}

// Configuração da sessão e cookies
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
session_start();

// Verifica se existe um cookie de login
if (isset($_COOKIE['cookie'])) {
    $cookie = $_COOKIE['cookie'];

    // Consulta o banco de dados para verificar se o cookie existe
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE cookie = ?");
    $stmt->execute([$cookie]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['numero'];
        if ($user['login'] == 'admin' || $user['nome'] == 'admin' || $user['nivel'] == 2) {
            header("Location: ./administracao/admin.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}




// Lógica de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (!$login || !$password) {
        $error = "Por favor, preencha todos os campos.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['numero'];

            if (isset($_POST['remember_me'])) {
                $cookie = bin2hex(random_bytes(16));
                $update_stmt = $conn->prepare("UPDATE utilizadores SET cookie = ? WHERE numero = ?");
                $update_stmt->execute([$cookie, $user['numero']]);
                setcookie('cookie', $cookie, time() + (86400 * 30), "/");
            }

            // Verificar se o MFA está ativado para o usuário
            if ($user['mfa'] == 1) {
                // Se MFA estiver ativo, redireciona para a página de verificação do MFA
                $_SESSION['user_id'] = $user['numero'];
                $_SESSION['mfa_login'] = true; // Marca que o login foi bem-sucedido, mas precisa do código MFA
                header("Location: verificar_mfa.php"); // Redireciona para a página de verificação MFA
                exit();
            }

            $session_id = session_id();
            $update_session_stmt = $conn->prepare("UPDATE utilizadores SET session_id = ? WHERE numero = ?");
            $update_session_stmt->execute([$session_id, $user['numero']]);



            registrarLog("Login bem-sucedido para o usuário: $login.");
            header("Location: " . ($user['nivel'] == 2 ? "./administracao/admin.php" : "index.php"));
            exit();
        } else {
            $_SESSION['tentativas_login'] = ($_SESSION['tentativas_login'] ?? 0) + 1;
            if ($_SESSION['tentativas_login'] >= 5) {
                $_SESSION['bloqueio_tempo'] = time() + 300;
                bloquearIP(40);
                $_SESSION['tentativas_login'] = 0;

            }
            registrarLog("Tentativa de login falhada para o usuário: $login.");
            $error = "Login ou password incorretos!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<div class="blur-background"></div>

<header>
    <h1>Login</h1>
</header>

<div class="container">
    <h2>Bem-vindo</h2>

    <!-- Exibe a mensagem de erro, caso exista -->
    <?php if (isset($error)): ?>
        <p class="error-message"><?php echo $error; ?></p>
    <?php endif; ?>

    <!-- Formulário de login -->
    <form action="" method="POST">
        <label for="login">Login:</label>
        <input type="text" name="login" required>

        <label for="password">password:</label>
        <input type="password" name="password" required>

        <!-- Checkbox "Remember Me" -->
        <label>
            <input type="checkbox" name="remember_me"> Lembrar-me
        </label>

        <button type="submit">Entrar</button>
    </form>

    <!-- Links para recuperar password e criar conta -->
    <p><a href="./recuperar_password/recover.php">Esqueci a Password</a></p>
    <p><a href="create.php">Criar conta</a></p>
</div>

<div class="container">
    <form action="index.php" method="GET">
        <button type="submit">Continuar como visitante</button>
    </form>

</div>

<footer>
    <p>&copy; 2024 Gestão de Salas. Todos os direitos reservados.</p>
</footer>

</body>
</html>
