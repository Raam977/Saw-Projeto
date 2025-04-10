<?php
// Inicializa variáveis para armazenar erros e dados do formulário
$errors = [];
$dados = [];

// Função para validar o código de estudante
function validarCodigo($codigo) {
    return filter_var($codigo, FILTER_VALIDATE_INT) !== false && strlen($codigo) === 7;
}

// Função para validar o primeiro nome
function validarPrimeiroNome($primeiro_nome) {
    return strlen($primeiro_nome) >= 3;
}

// Função para validar o último nome
function validarUltimoNome($ultimo_nome) {
    return strlen($ultimo_nome) >= 3;
}

// Função para validar o gênero
function validarGenero($genero) {
    return in_array($genero, ['M', 'F', 'O']);
}

// Função para validar o telefone
function validarTelefone($telefone) {
    return preg_match('/^(255\d{6}|(91|92|93|96)\d{7})$/', $telefone);
}

// Função para validar o curso
function validarCurso($curso) {
    return in_array($curso, ['LEI', 'SIRC', 'DWDM']);
}

// Função para validar o email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para validar a URL
function validarUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}



// Processa o formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_STRING);
    if (!validarCodigo($codigo)) {
        $errors['codigo'] = "O código de estudante deve ser um inteiro de 7 dígitos.";
    } else {
        $dados['codigo'] = $codigo;
    }

    $primeiro_nome = filter_input(INPUT_POST, 'primeiro_nome', FILTER_SANITIZE_STRING);
    if (!validarPrimeiroNome($primeiro_nome)) {
        $errors['primeiro_nome'] = "O primeiro nome deve ter no mínimo 3 caracteres.";
    } else {
        $dados['primeiro_nome'] = $primeiro_nome;
    }

    $ultimo_nome = filter_input(INPUT_POST, 'ultimo_nome', FILTER_SANITIZE_STRING);
    if (!validarUltimoNome($ultimo_nome)) {
        $errors['ultimo_nome'] = "O último nome deve ter no mínimo 3 caracteres.";
    } else {
        $dados['ultimo_nome'] = $ultimo_nome;
    }

    $genero = filter_input(INPUT_POST, 'genero', FILTER_SANITIZE_STRING);
    if (!validarGenero($genero)) {
        $errors['genero'] = "Selecione um gênero válido.";
    } else {
        $dados['genero'] = $genero;
    }


    // Aqui você pode chamar a função validarTelefone
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_NUMBER_INT);
    if (!empty($telefone) && !validarTelefone($telefone)) {
        $errors['telefone'] = "O telefone deve começar por 255 e ter mais 6 dígitos ou começar por 91, 92, 93 ou 96 e ter mais 7 dígitos.";
    } else {
        $dados['telefone'] = $telefone;
    }


    $curso = filter_input(INPUT_POST, 'curso', FILTER_SANITIZE_STRING);
    if (!validarCurso($curso)) {
        $errors['curso'] = "Selecione um curso válido.";
    } else {
        $dados['curso'] = $curso;
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (!validarEmail($email)) {
        $errors['email'] = "Insira um endereço de email válido.";
    } else {
        $dados['email'] = $email;
    }

    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    if (!empty($url) && !validarUrl($url)) {
        $errors['url'] = "Insira um URL válido.";
    } else {
        $dados['url'] = $url;
    }




    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $observacoes = str_replace(['"', "'"], '', $observacoes); // Remove aspas duplas e simples
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
</head>
<body>
<h2>Formulário de Inscrição de Aluno</h2>
<form action="" method="POST">
    <label for="codigo">Código de Estudante:</label>
    <input type="text" id="codigo" name="codigo" required pattern="\d{7}" title="Deve ser um inteiro de 7 dígitos" value="<?php echo isset($dados['codigo']) ? $dados['codigo'] : ''; ?>">
    <?php if (isset($errors['codigo'])) echo "<p class='error'>{$errors['codigo']}</p>"; ?>
    <br><br>

    <label for="primeiro_nome">Primeiro Nome:</label>
    <input type="text" id="primeiro_nome" name="primeiro_nome" required minlength="3" title="Deve ter no mínimo 3 caracteres" value="<?php echo isset($dados['primeiro_nome']) ? $dados['primeiro_nome'] : ''; ?>">
    <?php if (isset($errors['primeiro_nome'])) echo "<p class='error'>{$errors['primeiro_nome']}</p>"; ?>
    <br><br>

    <label for="ultimo_nome">Último Nome:</label>
    <input type="text" id="ultimo_nome" name="ultimo_nome" required minlength="3" title="Deve ter no mínimo 3 caracteres" value="<?php echo isset($dados['ultimo_nome']) ? $dados['ultimo_nome'] : ''; ?>">
    <?php if (isset($errors['ultimo_nome'])) echo "<p class='error'>{$errors['ultimo_nome']}</p>"; ?>
<br><br>

    <label for="genero">Gênero:</label>
    <select id="genero" name="genero" required>
        <option value="">Selecione...</option>
        <option value="M" <?php echo (isset($dados['genero']) && $dados['genero'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
        <option value="F" <?php echo (isset($dados['genero']) && $dados['genero'] === 'F') ? 'selected' : ''; ?>>Feminino</option>
        <option value="O" <?php echo (isset($dados['genero']) && $dados['genero'] === 'O') ? 'selected' : ''; ?>>Outro</option>
    </select>
    <?php if (isset($errors['genero'])) echo "<p class='error'>{$errors['genero']}</p>"; ?>

    <br><br>
    <label for="telefone">Telefone (Opcional):</label>
    <input type="tel" id="telefone" name="telefone" pattern="^(255\d{6}|(91|92|93|96)\d{7}$" title="O telefone deve começar por 255, 91, 92, 93 ou 96 e ter 9 dígitos." value="<?php echo isset($dados['telefone']) ? $dados['telefone'] : ''; ?>">
    <?php if (isset($errors['telefone'])) echo "<p class='error'>{$errors['telefone']}</p>"; ?>

    <br><br>
    <label for="curso">Curso:</label>
    <select id="curso" name="curso" required>
        <option value="">Selecione...</option>
        <option value="LEI" <?php echo (isset($dados['curso']) && $dados['curso'] === 'LEI') ? 'selected' : ''; ?>>LEI</option>
        <option value="SIRC" <?php echo (isset($dados['curso']) && $dados['curso'] === 'SIRC') ? 'selected' : ''; ?>>SIRC</option>
        <option value="DWDM" <?php echo (isset($dados['curso']) && $dados['curso'] === 'DWDM') ? 'selected' : ''; ?>>DWDM</option>
    </select>
    <?php if (isset($errors['curso'])) echo "<p class='error'>{$errors['curso']}</p>"; ?>
<br><br>
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required value="<?php echo isset($dados['email']) ? $dados['email'] : ''; ?>">
    <?php if (isset($errors['email'])) echo "<p class='error'>{$errors['email']}</p>"; ?>
<br><br>

    <label for="url">URL:</label>
    <input type="url" id="url" name="url"  value="<?php echo isset($dados['url']) ? $dados['url'] : ''; ?>">
    <?php if (isset($errors['url'])) echo "<p class='error'>{$errors['url']}</p>"; ?>

    <br><br>
    <label for="observacoes">Observações (Opcional):</label>
    <textarea id="observacoes" name="observacoes"><?php echo isset($dados['observacoes']) ? $dados['observacoes'] : ''; ?></textarea>
<br><br>
    <input type="submit" value="Enviar">
</form>
</body>
</html>
