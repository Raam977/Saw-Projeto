<?php
// Inclui o arquivo de configuração para a conexão com o banco de dados
include 'config.php';
include 'verificar_bloqueio.php';
date_default_timezone_set('Europe/Lisbon');


// Verifica se a variável $conn foi definida
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}




ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 0);
// Inicia a sessão
session_start();

// Botão de logout
if (isset($_POST['logout'])) {
    session_destroy();
    setcookie('cookie', '', time() - 3600, '/');
    header("Location: login.php");
    exit();
}

// Verifica se o usuário está autenticado
$user_id = $_SESSION['user_id'] ?? null;

// Se o usuário não estiver logado, redireciona para a página de login
if (!$user_id) {
    echo "<p>Por favor, faça login para requisitar uma sala.</p>";
    echo '<a href="login.php"><button>Login</button></a>';
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






// Função para buscar salas disponíveis
function buscarSalasDisponiveis($data, $horaInicio, $horaFim) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM salas 
        WHERE id NOT IN (
            SELECT sala_id 
            FROM reservas 
            WHERE data_reserva = ? 
            AND (
                (? BETWEEN hora_inicio AND hora_fim) OR 
                (? BETWEEN hora_inicio AND hora_fim) OR
                (hora_inicio BETWEEN ? AND ?) OR
                (hora_fim BETWEEN ? AND ?)
            )
        )
    ");
    $stmt->execute([$data, $horaInicio, $horaFim, $horaInicio, $horaFim, $horaInicio, $horaFim]);
    return $stmt->fetchAll();
}

// Função para buscar salas indisponíveis
function buscarSalasIndisponiveis($data, $horaInicio, $horaFim) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT salas.id, salas.nome, reservas.data_reserva, reservas.hora_inicio, reservas.hora_fim, utilizadores.nome AS usuario
        FROM reservas
        JOIN salas ON reservas.sala_id = salas.id
        JOIN utilizadores ON reservas.user_id = utilizadores.numero
        WHERE reservas.data_reserva = ?
        AND (
            (? BETWEEN reservas.hora_inicio AND reservas.hora_fim) OR 
            (? BETWEEN reservas.hora_inicio AND reservas.hora_fim) OR
            (reservas.hora_inicio BETWEEN ? AND ?) OR
            (reservas.hora_fim BETWEEN ? AND ?)
        )
        ORDER BY reservas.data_reserva, reservas.hora_inicio
    ");
    $stmt->execute([$data, $horaInicio, $horaFim, $horaInicio, $horaFim, $horaInicio, $horaFim]);
    return $stmt->fetchAll();
}

// Quando o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? null;
    $horaInicio = $_POST['hora_inicio'] ?? null;
    $horaFim = $_POST['hora_fim'] ?? null;

    // Verifica se a data e hora selecionadas são anteriores à data e hora atuais
    $currentDateTime = new DateTime();
    $selectedDateTime = new DateTime($data . ' ' . $horaInicio);



    // Se a data e hora selecionadas forem anteriores à data e hora atuais
    if ($selectedDateTime < $currentDateTime) {
        $erro = "Não é possível requisitar uma sala para uma data e hora no passado.";
    }
    // Verifica se o horário está entre 22:00 e 07:00
    elseif ($horaInicio < '07:00' || $horaInicio >= '22:00' || $horaFim <= '07:00' || $horaFim > '22:00') {
        $erro = "Reservas não são permitidas entre 22:00 e 07:00.";
    } elseif ($horaInicio && $horaFim) {
        // Calcular a diferença entre hora de início e hora de fim
        $inicio = strtotime($horaInicio);
        $fim = strtotime($horaFim);
        $diferencaHoras = ($fim - $inicio) / 3600;  // Converte a diferença para horas

        // Verifica se a diferença está entre 15 minutos (0.25 horas) e 8 horas
        if ($diferencaHoras < 0.25) {
            $erro = "A reserva deve ser no mínimo 15 minutos.";
        } elseif ($diferencaHoras > 8) {
            $erro = "A reserva não pode exceder 8 horas.";
        } else {
            // Busca salas disponíveis e indisponíveis
            $salasDisponiveis = buscarSalasDisponiveis($data, $horaInicio, $horaFim);
            $salasIndisponiveis = buscarSalasIndisponiveis($data, $horaInicio, $horaFim);
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}



// Função para registrar logs
function registrarLog($mensagem) {
    global $conn;

    // Diretório e arquivo de log
    $diretorioLogs = __DIR__ . '/../logs';
    $arquivoLog = $diretorioLogs . '/Requesitar_salas_logs.txt';

    // Cria o diretório logs se não existir
    if (!file_exists($diretorioLogs)) {
        mkdir($diretorioLogs, 0777, true);
    }

    // Obtém o user_id da sessão
    $user_id = $_SESSION['user_id'] ?? null;

    // Busca o nome do usuário no banco de dados, caso o user_id exista
    $userName = 'Usuário desconhecido';
    if ($user_id) {
        $stmt = $conn->prepare("SELECT nome FROM utilizadores WHERE numero = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $userName = $user['nome'] ?? $userName;
    }

    // Adiciona a data, hora e o nome do usuário à mensagem de log
    $dataHora = date('Y-m-d H:i:s');
    $mensagemLog = "[$dataHora] Usuário: $userName (ID: $user_id) - $mensagem\n";

    // Escreve a mensagem no arquivo de log
    file_put_contents($arquivoLog, $mensagemLog, FILE_APPEND);
}


// Quando o botão "Requisitar" for pressionado
if (isset($_POST['requisitar'])) {
    $salaId = $_POST['sala_id'] ?? null;
    $data = $_POST['data'];
    $horaInicio = $_POST['hora_inicio'];
    $horaFim = $_POST['hora_fim'];

    if ($salaId) {
        // Insere a reserva
        $stmt = $conn->prepare("
            INSERT INTO reservas (sala_id, user_id, data_reserva, hora_inicio, hora_fim) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$salaId, $user_id, $data, $horaInicio, $horaFim]);

        // Obtém o ID da reserva inserida
        $reservaId = $conn->lastInsertId();

        $mensagem = "reservou a sala $salaId para a data $data das $horaInicio às $horaFim. A reserva tem o (ID: $reservaId) ";
        registrarLog($mensagem);

        echo "<p>Reserva feita com sucesso!</p>";
        header("Refresh:2; url=index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<link rel="stylesheet" href="css/style.css">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisitar Sala</title>
</head>
<body>
<!-- Botão de Logout para usuários logados -->
<?php if ($user_id): ?>
    <form action="" method="POST">
        <button type="submit" name="logout">Logout</button>
    </form>
<?php endif; ?>

<!-- Botão para requisitar sala -->
<a href="index.php"><button>Voltar</button></a>


<h1>Requisitar Sala</h1>

<!-- Formulário para selecionar data e hora -->
<form method="POST">
    <label for="data">Data:</label>
    <input type="date" id="data" name="data" required><br><br>

    <label for="hora_inicio">Hora de Início:</label>
    <input type="time" id="hora_inicio" name="hora_inicio" required><br><br>

    <label for="hora_fim">Hora de Fim:</label>
    <input type="time" id="hora_fim" name="hora_fim" required><br><br>

    <button type="submit">Buscar Salas Disponíveis</button>

    <?php if (isset($erro)): ?>
        <p style="color: red;"><?= $erro ?></p>
    <?php endif; ?>

</form>

<!-- Exibir Salas Disponíveis -->
<?php
if (isset($_POST['data'], $_POST['hora_inicio'], $_POST['hora_fim'])) {
    // Receber os dados do formulário
    $data = $_POST['data'];
    $horaInicio = $_POST['hora_inicio'];
    $horaFim = $_POST['hora_fim'];

    // Verificar as salas disponíveis (estado "disponível") e sem reservas no horário selecionado
    $stmt = $conn->prepare("
        SELECT salas.id, salas.nome
        FROM salas
        LEFT JOIN reservas ON salas.id = reservas.sala_id
        AND reservas.data_reserva = :data
        AND ((reservas.hora_inicio < :hora_fim AND reservas.hora_fim > :hora_inicio))
        WHERE salas.estado = 'disponível' AND reservas.sala_id IS NULL
    ");

    $stmt->execute([
        ':data' => $data,
        ':hora_inicio' => $horaInicio,
        ':hora_fim' => $horaFim
    ]);

    $salasDisponiveis = $stmt->fetchAll();
}
?>

<?php if (isset($salasDisponiveis)): ?>
    <h2>Salas Disponíveis</h2>
    <table border="1">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Status</th>
            <th>Ação</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($salasDisponiveis) > 0): ?>
            <?php foreach ($salasDisponiveis as $sala): ?>
                <tr>
                    <td><?= $sala['id'] ?></td>
                    <td><?= htmlspecialchars($sala['nome']) ?></td>
                    <td>Disponível</td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="sala_id" value="<?= $sala['id'] ?>">
                            <input type="hidden" name="data" value="<?= $data ?>">
                            <input type="hidden" name="hora_inicio" value="<?= $horaInicio ?>">
                            <input type="hidden" name="hora_fim" value="<?= $horaFim ?>">
                            <button type="submit" name="requisitar">Requisitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">Nenhuma sala disponível para o horário selecionado.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>


<!-- Exibir Salas Indisponíveis -->
<?php if (isset($salasIndisponiveis)): ?>
    <h2>Salas Indisponíveis Por Reserva</h2>
    <table border="1">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Motivo</th>
            <th>Data</th>
            <th>Hora Início</th>
            <th>Hora Fim</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($salasIndisponiveis) > 0): ?>
            <?php foreach ($salasIndisponiveis as $sala): ?>
                <tr>
                    <td><?= $sala['id'] ?></td>
                    <td><?= htmlspecialchars($sala['nome']) ?></td>
                    <td>Reservado por <?= htmlspecialchars($sala['usuario']) ?></td>
                    <td><?= $sala['data_reserva'] ?></td>
                    <td><?= $sala['hora_inicio'] ?></td>
                    <td><?= $sala['hora_fim'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Nenhuma sala indisponível no horário selecionado.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>


<h2>Salas Indisponíveis Por outros motivos</h2>
<!-- Tabela com as salas indisponíveis -->
<table border="1">
    <thead>
    <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Descrição</th>
        <th>Estado</th>
    </tr>
    </thead>
    <tbody>
    <?php
    // Inclui o arquivo de configuração para a conexão com o banco de dados
    include 'config.php';

    // Verifica se a variável $conn foi definida
    if (!isset($conn)) {
        die("Erro de conexão com o banco de dados.");
    }

    // Consulta para buscar salas com estado 'indisponível' ou 'brevemente'
    $stmt = $conn->prepare("SELECT id, nome, descricao, estado FROM salas WHERE estado IN ('indisponível', 'brevemente')");
    $stmt->execute();
    $salasIndisponiveis = $stmt->fetchAll();

    if (count($salasIndisponiveis) > 0):
        foreach ($salasIndisponiveis as $sala):
            ?>
            <tr>
                <td><?= $sala['id'] ?></td>
                <td><?= $sala['nome'] ?></td>
                <td><?= htmlspecialchars($sala['descricao']) ?></td>
                <td><?= htmlspecialchars($sala['estado']) ?></td> <!-- Exibe o estado da sala -->
            </tr>
        <?php
        endforeach;
    else:
        ?>
        <tr>
            <td colspan="4">Nenhuma sala está indisponível ou brevemente indisponível no momento.</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>



<?php if (isset($erro)): ?>
    <p style="color: red;"><?= $erro ?></p>
<?php endif; ?>
</body>
</html>
