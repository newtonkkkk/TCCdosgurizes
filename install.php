<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$ok = false;
$erro = null;
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim((string)($_POST['confirm'] ?? ''));
    if ($confirm !== 'RECRIAR') {
        $erro = 'Digite RECRIAR no campo de confirmação para apagar e recriar o banco.';
    } else {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');

            $sqlFile = __DIR__ . '/sql/schema.sql';
            if (!is_readable($sqlFile)) {
                throw new RuntimeException('Arquivo sql/schema.sql não encontrado.');
            }
            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $parts = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($parts as $stmt) {
                if ($stmt === '') continue;
                $pdo->exec($stmt);
                $log[] = substr($stmt, 0, 70) . '...';
            }

            $hash = password_hash('root', PASSWORD_DEFAULT);
            $pdo->exec('USE `' . DB_NAME . '`');
            $st = $pdo->prepare('UPDATE usuarios SET senha = ?');
            $st->execute([$hash]);

            $ok = true;
        } catch (Throwable $e) {
            $erro = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Instalação · Almoxarifado</title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>Instalar banco</h1>
        <p class="lead">Cria o MySQL <code><?= htmlspecialchars(DB_NAME) ?></code>, tabelas e dados de demonstração.</p>
        <?php if ($ok): ?>
            <div class="toast toast-ok" style="margin:0 0 14px">Banco instalado. Senha de todos os perfis: <strong>root</strong></div>
            <a class="btn" href="index.php">Ir para o login</a>
        <?php else: ?>
            <?php if ($erro): ?><div class="toast toast-erro" style="margin:0 0 14px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <p class="muted">Confirme no <code>includes/config.php</code>: host <?= htmlspecialchars(DB_HOST) ?>, user <?= htmlspecialchars(DB_USER) ?>.</p>
            <form method="post" style="margin-top:16px">
                <label>Confirmação (digite <strong>RECRIAR</strong>)</label>
                <input type="text" name="confirm" placeholder="RECRIAR" autocomplete="off" required style="margin-bottom:12px">
                <button class="btn" type="submit">Criar / recriar banco</button>
            </form>
            <p class="muted" style="margin-top:12px;color:#ff6b6b">Isto apaga e recria o banco <code><?= htmlspecialchars(DB_NAME) ?></code>. Todos os dados serão perdidos.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
