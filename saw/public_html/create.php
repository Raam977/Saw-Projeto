<?php
// Inclui o arquivo de configuração para conectar ao banco de dados
include 'config.php';
include 'verificar_bloqueio.php';
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}
date_default_timezone_set('Europe/Lisbon');

require '../vendor/autoload.php';

include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/FixedBitNotation.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleAuthenticator.php';
include_once __DIR__.'/../vendor/sonata-project/google-authenticator/src/GoogleQrUrl.php';

use Google\Authenticator\GoogleAuthenticator;
// Funções de validação
function validarPrimeiroNome($nome) {
    // Caminho do arquivo de log

    $diretorioLogs = __DIR__ . '/../logs';
    $logFile = $diretorioLogs . '/nome_validacao.log';


    // Função para registrar logs
    function registrarLog($mensagem, $logFile) {
        if (!file_exists($logFile)) {
            file_put_contents($logFile, "Log de validação de nomes\n", FILE_APPEND);
        }

        $hora = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$hora] $mensagem\n", FILE_APPEND);
    }

    // Validação inicial com regex
    $nomeValido = preg_match("/^[a-zA-ZÀ-ÿ\s]{3,}$/", $nome);

    if (!$nomeValido) {
        registrarLog("Nome '$nome' inválido pela regex.", $logFile);
        return false; // Retorna falso imediatamente se falhar na regex
    }

    // Validação adicional com a API
    try {
        $resultadoAPI = verificarNome($nome); // Chama a função que usa a NameAPI

        // Log do resultado da API
        if ($resultadoAPI) {
            registrarLog("Nome '$nome' validado com sucesso pela API.", $logFile);
        } else {
            registrarLog("Nome '$nome' não encontrado como válido pela API.", $logFile);
        }

        // Retorna verdadeiro se o nome é válido pela regex ou pela API
        return $resultadoAPI;
    } catch (Exception $e) {
        // Log de erro
        registrarLog("Erro ao verificar o nome '$nome' na API: " . $e->getMessage(), $logFile);

        // Se houver erro na API, consideramos somente a regex
        return $nomeValido;
    }
}

// Função para verificar se o e-mail é temporário
function verificarEmailTemporario($email) {
    // Initialize cURL.
    $ch = curl_init();
    $apiKey = "apikey";
    // Set the URL that you want to GET by using the CURLOPT_URL option.
    $url = "https://emailvalidation.abstractapi.com/v1/?api_key=$apiKey&email=$email";

    // Set the URL that you want to GET by using the CURLOPT_URL option.
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Set CURLOPT_FOLLOWLOCATION to true to follow redirects.
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Execute the request.
    $data = curl_exec($ch);

    // Close the cURL handle.
    curl_close($ch);

    $diretorioLogs = __DIR__ . '/../logs';
    $logFile = $diretorioLogs . '/email_validacao.log';
    registrarLog("Email temporario ; $data", $logFile);

    // Decodifica a resposta JSON
    $resultado = json_decode($data, true);

    // Verifica se o e-mail é temporário
    if (isset($resultado['is_temporary_email']) && $resultado['is_temporary_email']) {
        registrarLog("Email temporario ; $resultado", $logFile);
        return true; // E-mail temporário

    }
    // Verifica o quality_score
    if (isset($resultado['quality_score']) && $resultado['quality_score'] < 0.5) {
        registrarLog("Quality score baixo ({$resultado['quality_score']}): $email", $logFile);
        return true; // Email com qualidade baixa
    }

   // registrarLog("Email não temporario ; " . print_r($resultado, true), $logFile);
    registrarLog("Email não temporario ; Quality score: {$resultado['quality_score']}", $logFile);

    return false; // E-mail não temporário
}


function verificarNome($nome) {
    $url = 'https://api.genderize.io?name=' . urlencode($nome);

    $diretorioLogs = __DIR__ . '/../logs';
    $logFile = $diretorioLogs . '/nome_validacao.log';

    // Inicializa o cURL
    $ch = curl_init($url);

    // Configurações do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Faz a requisição
    $resposta = curl_exec($ch);

    // Verifica erros no cURL
    if (curl_errno($ch)) {
        $erro = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erro na requisição: $erro");
    }

    curl_close($ch);

    // Decodifica a resposta JSON
    $resultado = json_decode($resposta, true);

    // Processa a resposta para verificar se o nome foi encontrado
    if (isset($resultado['gender'])) {
        $gender = $resultado['gender'];
        registrarLog("Nome '$nome' identificado como gênero: $gender pela API.", $logFile);
        return true; // Nome encontrado e identificado
    }

    registrarLog("Nome '$nome' não identificado pela API. Resposta: $resposta", $logFile);
    return false; // Nome não identificado
}


function validarPassword($password) {
    if (strlen($password) < 8) {
        return "A password deve ter pelo menos 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "A password deve conter pelo menos uma letra maiúscula.";
    }
    if (!preg_match('/[\W_]/', $password)) {
        return "A password deve conter pelo menos um caractere especial (ex.: @, #, $, etc.).";
    }
    return true; // password válida
}

// Pasta onde as fotos serão salvas
define('UPLOAD_DIR', 'uploads/');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inserir'])) {
    // Sanitiza e valida o input do utilizador
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    if (!validarPrimeiroNome($nome)) {
        $errors['nome'] = "O nome deve ter pelo menos 3 caracteres e conter apenas letras. O nome não deve ser aleatorio";
    }

    $login = htmlspecialchars($_POST['login'], ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];
    $passwordValida = validarPassword($password);

    if ($passwordValida !== true) {
        $errors['password'] = $passwordValida;
    }
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    // Validação básica do formato do e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        //$errors['email'] = "E-mail inválido!";
        //echo $errors['email']; // Exibe a mensagem de erro
        //return;
        $errors['email'] = "E-mails inválido!";
    }

// Função para validar e-mail temporário e qualidade
    if (verificarEmailTemporario($email)) {
        //$errors['email'] = "E-mails temporários ou de baixa qualidade não são válidos!";
        //echo $errors['email']; // Exibe a mensagem de erro
        //return "E-mails temporários ou de baixa qualidade não são válidos!";
        $errors['email'] = "E-mails temporários ou de baixa qualidade não são válidos!";
    }



    // Verifica se o upload do arquivo foi realizado
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto = $_FILES['foto'];

        // Validações do arquivo
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extensao = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));

        if (!in_array($extensao, $extensoesPermitidas)) {
            $errors['foto'] = "Formato de imagem inválido. Permitido: JPG, JPEG, PNG, GIF.";
        }

        if ($foto['size'] > 2 * 1024 * 1024) { // Limite de 2 MB
            $errors['foto'] = "A foto deve ter no máximo 2MB.";
        }

        if (empty($errors['foto'])) {
            // Gera um nome único para a imagem
            $nomeFoto = uniqid('foto_', true) . '.' . $extensao;
            $caminhoFoto = UPLOAD_DIR . $nomeFoto;

            // Move o arquivo para a pasta de upload
            if (!move_uploaded_file($foto['tmp_name'], $caminhoFoto)) {
                $errors['foto'] = "Erro ao salvar a foto. Tente novamente.";
            }
        }
    } else {
        $caminhoFoto = null; // Foto é opcional
    }

    // Verifica se o usuário marcou a opção de MFA
    $mfaAtivo = isset($_POST['mfa']) ? 1 : 0;  // 1 se ativado, 0 se não

    // Apenas prossegue se não houver erros
    if (empty($errors)) {
        try {
            // Verifica se o login ou email já existem no banco de dados
            $stmt = $conn->prepare("SELECT COUNT(*) FROM utilizadores WHERE login = ? OR email = ?");
            $stmt->execute([$login, $email]);
            $existe = $stmt->fetchColumn();

            if ($existe > 0) {
                $errors['login_email'] = "Login ou e-mail já estão em uso. Por favor, escolha outros.";
            } else {
                // Hash da password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Geração do segredo MFA caso a opção tenha sido ativada
                $mfaSecret = null;
                $qrCodeUrl = null;


                // Nível fixo para novos utilizadores
                $nivel = 1;



                // Se MFA está ativado, cria o código QR
                // Se MFA está ativado, cria o código QR
                if ($mfaAtivo) {
                    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();

                    // Geração do segredo MFA
                    $secret = $g->generateSecret();  // Gera o segredo MFA

                    // Armazena o segredo gerado no banco de dados
                    $mfaSecret = $secret;  // Atribui o segredo gerado à variável $mfaSecret

                    // Uso de prepared statements para evitar SQL Injection
                    $stmt = $conn->prepare("INSERT INTO utilizadores (nome, login, password, nivel, email, foto_perfil, mfa, mfa_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $login, $passwordHash, $nivel, $email, $caminhoFoto, $mfaAtivo, $mfaSecret]);

                    // Usuário ou identificador para o QR Code
                    $username = 'chregu'; // Isso pode ser o nome de usuário do seu sistema

                    // Geração da URL do QR Code
                    $qrCodeUrl = \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($username, $secret, 'Gestão de salas');

                    // Exibe a URL do QR Code
                    echo "O QR Code para esse segredo (escaneie com o Google Authenticator App) está disponível aqui:\n";
                    echo '<img src="' . $qrCodeUrl . '" alt="QR Code para Google Authenticator">';

                    echo "\nVai ser redirecionado para o login em 20 segundos\n";
                    header("refresh:20; url=login.php");
                } else {
                    // Se MFA não estiver ativado, insere os dados normalmente
                    $stmt = $conn->prepare("INSERT INTO utilizadores (nome, login, password, nivel, email, foto_perfil, mfa, mfa_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $login, $passwordHash, $nivel, $email, $caminhoFoto, 0, null]);
                    echo "<p>MFA não ativado. Você será redirecionado para o login...</p>";
                    header("refresh:3; url=login.php");
                }



                // Redireciona para a página de login após sucesso
                //header("Location: login.php");
                //exit;
            }
        } catch (PDOException $e) {
            // Registra o erro no log e exibe uma mensagem genérica para o utilizador
            error_log($e->getMessage());
            $errors['database'] = "Erro ao criar a conta. Por favor, tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Novo Utilizador</title>
    <link href="'https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/create.css">
</head>
<body>

<div class="blur-background"></div>

<header>
    <h1>Criar Novo Utilizador</h1>
</header>

<div class="container">
    <h2>Preencha os dados</h2>

    <!-- Exibe as mensagens de erro -->
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Formulário de criação de utilizador -->
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="nome">Nome:</label>
        <input type="text" name="nome" required>

        <label for="login">UserName:</label>
        <input type="text" name="login" required>

        <label for="password">Password:</label>
        <input type="password" name="password" required>

        <label for="email">E-mail:</label>
        <input type="email" name="email" required>

        <label for="foto">Foto de Perfil (opcional):</label>
        <input type="file" name="foto" accept="image/*">

        <label for="mfa">Ativar Autenticação de Múltiplos Fatores (MFA):</label>
        <input type="checkbox" name="mfa" id="mfa">


        <button type="submit" name="inserir">Criar Utilizador</button>
    </form>

    <p><a href="login.php">Já tem uma conta? Faça login aqui.</a></p>
</div>

<footer>
    <p>&copy; 2024 Gestão de Salas. Todos os direitos reservados.</p>
</footer>

</body>
</html>
