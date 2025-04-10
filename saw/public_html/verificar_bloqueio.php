<?php


include 'config.php';
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

date_default_timezone_set('Europe/Lisbon');

function verificarBloqueio() {
    global $conn;
    $ipPublico = file_get_contents('https://api.ipify.org') ?: 'IP público desconhecido';


    // Verifique se o IP está bloqueado
    $stmt = $conn->prepare("SELECT desbloqueio_em FROM bloqueios WHERE ip = ? AND desbloqueio_em > NOW()");
    $stmt->execute([$ipPublico]);
    $bloqueio = $stmt->fetch();

    if ($bloqueio) {
        $tempoRestante = strtotime($bloqueio['desbloqueio_em']) - time();
        die("Seu acesso está temporariamente bloqueado. Tente novamente em $tempoRestante segundos.");
    }
}



$stmt = $conn->prepare("DELETE FROM bloqueios WHERE desbloqueio_em <= NOW()");
$stmt->execute();

verificarBloqueio();