<?php
// Inclui o arquivo de configuração para a conexão com o banco de dados
include 'config.php';

// Verifica se a variável $conn foi definida
if (!isset($conn)) {
    die("Erro de conexão com o banco de dados.");
}

// Atualiza o estado das salas para "disponível" onde a data_fim da reserva já passou
$stmt = $conn->prepare("
    UPDATE salas 
    SET estado = 'disponível' 
    WHERE id IN (
        SELECT sala_id 
        FROM reservas 
        WHERE data_fim < NOW()
    )
");
$stmt->execute();

echo "Salas liberadas com sucesso!";
?>
