<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: login.php');
  exit();
}

require_once __DIR__ . '/conn.php';

function sanitizeField($value)
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$userId = null;
$statusNotice = null;
$loadError = '';

if ($userEmail !== '') {
  $stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
  if ($stmtUser) {
    $stmtUser->bind_param('s', $userEmail);
    $stmtUser->execute();
    $stmtUser->bind_result($foundUserId);
    if ($stmtUser->fetch()) {
      $userId = (int)$foundUserId;
    } else {
      $loadError = 'Usuário não encontrado no banco de dados.';
    }
    $stmtUser->close();
  } else {
    $loadError = 'Erro ao preparar consulta de usuário.';
  }
} else {
  $loadError = 'Sessão inválida. Faça login novamente.';
}

$enderecos = [];
if ($userId !== null) {
  $stmt = $conn->prepare('SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple FROM EnderecoEntrega WHERE UsuId = ? ORDER BY EndEntId DESC');
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $enderecos[] = $row;
    }
    $stmt->close();
  } else {
    $loadError = 'Erro ao carregar os endereços. Tente novamente.';
  }
}

$statusMap = [
  'added' => ['type' => 'success', 'text' => 'Endereço adicionado com sucesso.'],
  'updated' => ['type' => 'success', 'text' => 'Endereço atualizado com sucesso.'],
  'deleted' => ['type' => 'success', 'text' => 'Endereço excluído com sucesso.'],
  'error' => ['type' => 'error', 'text' => 'Não foi possível concluir a operação.']
];

if (isset($_GET['status']) && isset($statusMap[$_GET['status']])) {
  $statusNotice = $statusMap[$_GET['status']];
}

if ($loadError !== '') {
  $statusNotice = ['type' => 'error', 'text' => $loadError];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Endereços</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="/img/icone.ico">
  <link rel="stylesheet" href="../css/perfil.css">
</head>

<body>
  <div class="sidebar">
    <div class="profile">
      <img src="../perfil.png" alt="Perfil" />
      <p>Olá!</p>
      <p><?php echo sanitizeField($userName); ?></p>
    </div>
    <nav>
      <a href="perfil.php">Dados pessoais</a>
      <a href="enderecos.php" class="active">Endereços</a>
      <a href="pedidos.html">Pedidos</a>
      <a href="cartoes.html">Cartões</a>
      <a href="autenticacao.html">Autenticação</a>
      <a href="organizacao.html">Minha organização</a>
      <a href="favoritos.php">Favoritos</a>
      <a href="sair.php">Sair</a>
    </nav>
  </div>

  <div class="main-content">
    <h1>Endereços</h1>

    <?php if ($statusNotice) : ?>
      <div class="status-message status-<?php echo sanitizeField($statusNotice['type']); ?>">
        <?php echo sanitizeField($statusNotice['text']); ?>
      </div>
    <?php endif; ?>

    <div class="actions">
      <a class="button-link" href="addEnd.php">Adicionar endereço</a>
    </div>

    <?php if (empty($enderecos)) : ?>
      <div class="card empty-state">
        <p>Nenhum endereço cadastrado ainda.</p>
        <p>Use o botão "Adicionar endereço" para cadastrar o primeiro.</p>
      </div>
    <?php else : ?>
      <div class="info-section">
        <?php foreach ($enderecos as $endereco) : ?>
          <div class="card address-card">
            <p><strong><?php echo sanitizeField($endereco['EndEntRef']); ?></strong></p>
            <p class="address-meta">
              <?php
              $rua = sanitizeField($endereco['EndEntRua']);
              $numero = sanitizeField($endereco['EndEntNum']);
              $bairro = sanitizeField($endereco['EndEntBairro']);
              $cidade = sanitizeField($endereco['EndEntCid']);
              $estado = sanitizeField($endereco['EndEntEst']);
              $cep = sanitizeField($endereco['EndEntCep']);
              $complemento = sanitizeField($endereco['EndEntComple']);
              ?>
              <?php echo "$rua, $numero"; ?><br>
              <?php echo "$bairro - $cidade / $estado"; ?><br>
              CEP: <?php echo $cep; ?><br>
              <?php if ($complemento !== '') : ?>
                Complemento: <?php echo $complemento; ?>
              <?php endif; ?>
            </p>
            <div class="address-actions">
              <a class="button-link" href="editarEnd.php?id=<?php echo (int)$endereco['EndEntId']; ?>">Editar</a>
              <form action="deletarEnd.php" method="POST" onsubmit="return confirm('Deseja realmente excluir este endereço?');">
                <input type="hidden" name="endereco_id" value="<?php echo (int)$endereco['EndEntId']; ?>">
                <button type="submit">Excluir</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
      document.body.classList.add('dark');
    }

    function toggleTheme() {
      document.body.classList.toggle('dark');
      const theme = document.body.classList.contains('dark') ? 'dark' : 'light';
      localStorage.setItem('theme', theme);
    }
  </script>
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'light') {
        document.body.classList.add('light');
      }
    });
  </script>

</body>

</html>
