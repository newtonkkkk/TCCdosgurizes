<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$perfis = [];
$dbOk = true;
try {
    $perfis = db(false)->query("SELECT id, nome, perfil FROM usuarios WHERE ativo = 1 ORDER BY FIELD(perfil,'dono','almoxarife','mecanico','financeiro'), nome")->fetchAll();
} catch (Throwable $e) {
    $dbOk = false;
}

$escolhido = null;
$pid = (int)($_GET['perfil'] ?? $_POST['perfil_id'] ?? 0);
if ($pid && $dbOk) {
    foreach ($perfis as $p) {
        if ((int)$p['id'] === $pid) {
            $escolhido = $p;
            break;
        }
    }
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        $senha = (string)($_POST['senha'] ?? '');
        $idPost = (int)($_POST['perfil_id'] ?? 0);
        if ($idPost <= 0 || $senha === '') {
            $erro = 'Escolha o perfil e informe a senha.';
        } elseif (!attempt_login_id($idPost, $senha)) {
            $erro = 'Senha incorreta.';
            foreach ($perfis as $p) {
                if ((int)$p['id'] === $idPost) {
                    $escolhido = $p;
                }
            }
        } else {
            redirect('/dashboard.php');
        }
    }
}
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#050608">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Quem está usando? · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css?v=5">
</head>
<body class="nf-body">
<div class="nf-bg"></div>
<header class="nf-top">
    <div class="nf-logo">OFICINA</div>
    <span class="nf-tag">Almoxarifado automotivo</span>
</header>

<main class="nf-main">
    <?php if (!$dbOk): ?>
        <h1 class="nf-title">Banco ainda não instalado</h1>
        <p class="nf-sub">Abra o instalador para criar as tabelas no XAMPP.</p>
        <a class="nf-btn" href="<?= e(BASE_URL) ?>/install.php">Instalar agora</a>
    <?php elseif ($escolhido): ?>
        <section class="nf-lock">
            <div class="nf-avatar nf-avatar-lg" style="background:<?= e(avatar_cor((int)$escolhido['id'])) ?>">
                <?= e(iniciais($escolhido['nome'])) ?>
            </div>
            <h1 class="nf-name"><?= e($escolhido['nome']) ?></h1>
            <p class="nf-role"><?= e(perfil_label($escolhido['perfil'])) ?></p>

            <?php if ($flash): ?><p class="nf-erro"><?= e($flash['msg']) ?></p><?php endif; ?>
            <?php if ($erro): ?><p class="nf-erro"><?= e($erro) ?></p><?php endif; ?>

            <form class="nf-form" method="post" autocomplete="current-password">
                <?= csrf_field() ?>
                <input type="hidden" name="perfil_id" value="<?= (int)$escolhido['id'] ?>">
                <label class="nf-label" for="senha">Senha do perfil</label>
                <input class="nf-input" id="senha" name="senha" type="password" required autofocus placeholder="Digite a senha">
                <button class="nf-btn" type="submit">Entrar</button>
            </form>
            <a class="nf-back" href="<?= e(BASE_URL) ?>/index.php">‹ Trocar de perfil</a>
            <p class="nf-hint">Senha de demonstração: <b>root</b></p>
        </section>
    <?php else: ?>
        <h1 class="nf-title">Quem está usando?</h1>
        <p class="nf-sub">Escolha um perfil para entrar na oficina.</p>
        <?php if ($flash): ?><p class="nf-erro"><?= e($flash['msg']) ?></p><?php endif; ?>
        <?php if ($erro): ?><p class="nf-erro"><?= e($erro) ?></p><?php endif; ?>

        <ul class="nf-grid">
            <?php foreach ($perfis as $p): ?>
                <li>
                    <a class="nf-card" href="<?= e(BASE_URL) ?>/index.php?perfil=<?= (int)$p['id'] ?>">
                        <div class="nf-avatar" style="background:<?= e(avatar_cor((int)$p['id'])) ?>">
                            <?= e(iniciais($p['nome'])) ?>
                        </div>
                        <span class="nf-card-name"><?= e($p['nome']) ?></span>
                        <span class="nf-card-role"><?= e(perfil_label($p['perfil'])) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="nf-hint">Toque no perfil e use a senha <b>root</b></p>
    <?php endif; ?>
</main>
</body>
</html>
