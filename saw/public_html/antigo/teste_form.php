<?php
// Inicializa variáveis para armazenar erros e dados do formulário
$errors = [];
$dados = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Filtra e valida os dados do formulário
    $codigo = filter_input(INPUT_POST, 'codigo', FILTER_VALIDATE_INT);
    if ($codigo === false || strlen($codigo) !== 7) {
        $errors['codigo'] = "O código de estudante deve ser um inteiro de 7 dígitos.";
    } else {
        $dados['codigo'] = $codigo;
    }

    $primeiro_nome = filter_input(INPUT_POST, 'primeiro_nome', FILTER_SANITIZE_STRING);
    if (strlen($primeiro_nome) < 3) {
        $errors['primeiro_nome'] = "O primeiro nome deve ter no mínimo 3 caracteres.";
    } else {
        $dados['primeiro_nome'] = $primeiro_nome;
    }

    $ultimo_nome = filter_input(INPUT_POST, 'ultimo_nome', FILTER_SANITIZE_STRING);
    if (strlen($ultimo_nome) < 3) {
        $errors['ultimo_nome'] = "O último nome deve ter no mínimo 3 caracteres.";
    } else {
        $dados['ultimo_nome'] = $ultimo_nome;
    }

    $genero = filter_input(INPUT_POST, 'genero', FILTER_SANITIZE_STRING);
    if (!in_array($genero, ['M', 'F', 'O'])) {
        $errors['genero'] = "Selecione um gênero válido.";
    } else {
        $dados['genero'] = $genero;
    }

    // Sanitiza e valida o telefone
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_NUMBER_INT);
    if (!empty($telefone) && !preg_match('/^(255|91|92|93|96)\d{7}$/', $telefone)) {
        $errors['telefone'] = "O telefone deve começar por 255, 91, 92, 93 ou 96 e ter 9 dígitos.";
    } else {
        // Certifica-se de que o telefone tenha 9 dígitos após a sanitização
        if (!empty($telefone) && strlen($telefone) !== 9) {
            $errors['telefone'] = "O telefone deve ter exatamente 9 dígitos.";
        } else {
            $dados['telefone'] = $telefone;
        }
    }

    $curso = filter_input(INPUT_POST, 'curso', FILTER_SANITIZE_STRING);
    if (!in_array($curso, ['LEI', 'SIRC', 'DWDM'])) {
        $errors['curso'] = "Selecione um curso válido.";
    } else {
        $dados['curso'] = $curso;
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        $errors['email'] = "Insira um endereço de email válido.";
    } else {
        $dados['email'] = $email;
    }

    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_STRING);
    $dados['observacoes'] = $observacoes;

    // Se não houver erros, exibe os dados coletados
    if (empty($errors)) {
        echo "<h2>Dados do Aluno</h2>";
        foreach ($dados as $key => $value) {
            echo "<p><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> " . ($value ?: 'N/A') . "</p>";
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de Inscrição de Aluno</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            max-width: 400px;
        }
        label {
            margin-top: 10px;
            display: block;
        }
        input[type="text"], input[type="number"], input[type="tel"], input[type="email"], select, textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        input[type="submit"] {
            margin-top: 10px;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
<h2>Formulário de Inscrição de Aluno</h2>
<form action="" method="POST">
    <label for="codigo">Código de Estudante:</label>
    <input type="number" id="codigo" name="codigo" required pattern="\d{7}" title="Deve ser um inteiro de 7 dígitos" value="<?php echo isset($dados['codigo']) ? $dados['codigo'] : ''; ?>">
    <?php if (isset($errors['codigo'])) echo "<p class='error'>{$errors['codigo']}</p>"; ?>

    <label for="primeiro_nome">Primeiro Nome:</label>
    <input type="text" id="primeiro_nome" name="primeiro_nome" required minlength="3" title="Deve ter no mínimo 3 caracteres" value="<?php echo isset($dados['primeiro_nome']) ? $dados['primeiro_nome'] : ''; ?>">
    <?php if (isset($errors['primeiro_nome'])) echo "<p class='error'>{$errors['primeiro_nome']}</p>"; ?>

    <label for="ultimo_nome">Último Nome:</label>
    <input type="text" id="ultimo_nome" name="ultimo_nome" required minlength="3" title="Deve ter no mínimo 3 caracteres" value="<?php echo isset($dados['ultimo_nome']) ? $dados['ultimo_nome'] : ''; ?>">
    <?php if (isset($errors['ultimo_nome'])) echo "<p class='error'>{$errors['ultimo_nome']}</p>"; ?>

    <label for="genero">Género:</label>
    <select id="genero" name="genero" required>
        <option value="">Selecione...</option>
        <option value="M" <?php echo (isset($dados['genero']) && $dados['genero'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
        <option value="F" <?php echo (isset($dados['genero']) && $dados['genero'] === 'F') ? 'selected' : ''; ?>>Feminino</option>
        <option value="O" <?php echo (isset($dados['genero']) && $dados['genero'] === 'O') ? 'selected' : ''; ?>>Outro</option>
    </select>
    <?php if (isset($errors['genero'])) echo "<p class='error'>{$errors['genero']}</p>"; ?>

    <label for="telefone">Telefone (Opcional):</label>
    <input type="tel" id="telefone" name="telefone" pattern="^(255|91|92|93|96)\d{7}$" title="Deve começar por 255, 91, 92, 93 ou 96 e ter 9 dígitos" value="<?php echo isset($dados['telefone']) ? $dados['telefone'] : ''; ?>">
    <?php if (isset($errors['telefone'])) echo "<p class='error'>{$errors['telefone']}</p>"; ?>

    <label for="curso">Curso:</label>
    <select id="curso" name="curso" required>
        <option value="">Selecione...</option>
        <option value="LEI" <?php echo (isset($dados['curso']) && $dados['curso'] === 'LEI') ? 'selected' : ''; ?>>LEI</option>
        <option value="SIRC" <?php echo (isset($dados['curso']) && $dados['curso'] === 'SIRC') ? 'selected' : ''; ?>>SIRC</option>
        <option value="DWDM" <?php echo (isset($dados['curso']) && $dados['curso'] === 'DWDM') ? 'selected' : ''; ?>>DWDM</option>
    </select>
    <?php if (isset($errors['curso'])) echo "<p class='error'>{$errors['curso']}</p>"; ?>

    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required value="<?php echo isset($dados['email']) ? $dados['email'] : ''; ?>">
    <?php if (isset($errors['email'])) echo "<p class='error'>{$errors['email']}</p>"; ?>

    <label for="observacoes">Observações (Opcional):</label>
    <textarea id="observacoes" name="observacoes" rows="4"><?php echo isset($dados['observacoes']) ? $dados['observacoes'] : ''; ?></textarea>

    <input type="submit" value="Enviar">
</form>
</body>
</html>
