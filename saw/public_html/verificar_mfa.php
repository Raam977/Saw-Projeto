<?php
// Inicia a sessão e inclui as dependências
global $conn;
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
session_start();
include 'config.php';
include 'verificar_bloqueio.php';
require '../vendor/autoload.php';

include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/FixedBitNotation.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleAuthenticator.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleQrUrl.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

date_default_timezone_set('Europe/Lisbon');
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




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém o código MFA enviado pelo usuário
    $mfa_code = $_POST['mfa_code'];

    // Recupera o segredo armazenado para o usuário
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE numero = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $secret = $user['mfa_secret']; // O segredo do MFA deve ser armazenado aqui

        // Verifica se o código inserido é válido
        $g = new GoogleAuthenticator();

        echo 'Current server time: ' . time() . "\n";
        echo 'Current Code is: ' . $g->getCode($secret) . "\n";
        echo 'Current server time: ' . date('Y-m-d H:i:s', time()) . "\n";
        echo $secret;


        $isCodeValid = $g->checkCode($secret, $mfa_code);
        var_dump($isCodeValid); // Verifique se o código é válido
        if ($isCodeValid) {
            $_SESSION['user_id'] = $user['numero'];
            $session_id = session_id();
            $update_session_stmt = $conn->prepare("UPDATE utilizadores SET session_id = ? WHERE numero = ?");
            $update_session_stmt->execute([$session_id, $user['numero']]);

            registrarLog("Login bem-sucedido para o usuário: $user com MFA: $mfa_code.");

            header("Location: " . ($user['nivel'] == 2 ? "./administracao/admin.php" : "index.php"));
            exit();
        } else {
            registrarLog("Login falhado para o usuário: $user com MFA: $mfa_code e secret: $secret.");

            $error = "Código MFA incorreto!";
        }
    } else {
        $error = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação MFA</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="container">
    <h2>Verificação de MFA</h2>

    <?php if (isset($error)): ?>
        <p class="error-message"><?php echo $error; ?></p>
    <?php endif; ?>

    <form action="verificar_mfa.php" method="POST">
        <label for="mfa_code">Código MFA:</label>
        <input type="text" name="mfa_code" required>

        <button type="submit">Verificar</button>
    </form>
</div>
</body>
</html>
