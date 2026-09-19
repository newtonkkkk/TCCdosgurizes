<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$pdo = db();
$alertas = alertas_estoque($pdo);
$qtdAlertas = count($alertas);
$pagina = basename($_SERVER['PHP_SELF']);
$perfil = current_perfil();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b0d11">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= e($pageTitle ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@360;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css?v=8">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <nav class="dock">
            <a class="<?= $pagina==='dashboard.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/dashboard.php" title="Painel">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>
                </span>
                <em>Painel</em>
            </a>

            <?php if (in_array($perfil, ['dono', 'mecanico'], true)): ?>
            <a class="<?= $pagina==='os.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/os.php" title="Ordens">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </span>
                <em>O.S.</em>
            </a>
            <a class="<?= $pagina==='agenda.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/agenda.php" title="Agenda">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                </span>
                <em>Agenda</em>
            </a>
            <a class="<?= $pagina==='clientes.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/clientes.php" title="Clientes">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.2-3.2 3.6-4.8 7-4.8s5.8 1.6 7 4.8"/></svg>
                </span>
                <em>Clientes</em>
            </a>
            <?php endif; ?>

            <?php if (can_gestao_pecas() || can_saida_os() || can_financeiro()): ?>
            <a class="<?= $pagina==='produtos.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/produtos.php?tab=<?= can_gestao_pecas() ? 'estoque' : (can_saida_os() && !can_financeiro() ? 'saida' : 'historico') ?>" title="Peças">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg>
                </span>
                <em>Peças</em>
            </a>
            <?php endif; ?>

            <?php if (can_gestao_pecas()): ?>
            <a class="<?= $pagina==='fornecedores.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/fornecedores.php" title="Fornecedores">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 20V9l9-5 9 5v11"/><path d="M9 20v-6h6v6"/></svg>
                </span>
                <em>Fornec.</em>
            </a>
            <?php endif; ?>

            <?php if (can_relatorio_estoque() || can_financeiro()): ?>
            <a class="<?= $pagina==='relatorios.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/relatorios.php" title="Relatórios">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 20V10M10 20V4M15 20v-7M20 20V8"/></svg>
                </span>
                <em>Relatórios</em>
            </a>
            <?php endif; ?>

            <?php if (can_financeiro()): ?>
            <a class="<?= $pagina==='pagamentos.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/pagamentos.php" title="Folha">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                </span>
                <em>Folha</em>
            </a>
            <?php endif; ?>

            <?php if (is_dono()): ?>
            <a class="<?= $pagina==='usuarios.php'?'active':'' ?>" href="<?= e(BASE_URL) ?>/pages/usuarios.php" title="Perfis">
                <span class="dock-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="2.6"/><circle cx="16" cy="9" r="2.2"/><path d="M4 19c.8-2.8 2.6-4.2 5-4.2s4.2 1.4 5 4.2M14 19c.4-1.6 1.4-2.6 3-2.6 1.4 0 2.4.8 3 2.6"/></svg>
                </span>
                <em>Perfis</em>
            </a>
            <?php endif; ?>
        </nav>

        <div class="dock-foot">
            <a class="dock-user" href="<?= e(BASE_URL) ?>/logout.php" title="Trocar perfil">
                <span class="avatar" style="background:<?= e(avatar_cor((int)$user['id'])) ?>"><?= e(iniciais($user['nome'])) ?></span>
                <em>Sair</em>
            </a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= e($pageTitle ?? 'Painel') ?></h1>
                <?php if (!empty($pageHint)): ?><p class="hint"><?= e($pageHint) ?></p><?php endif; ?>
            </div>
            <div class="top-actions">
                <?php if (can_gestao_pecas() || can_saida_os()): ?>
                <a class="alert-pill <?= $qtdAlertas ? 'hot' : '' ?>" href="<?= e(BASE_URL) ?>/pages/produtos.php?tab=<?= can_gestao_pecas() ? 'estoque' : 'saida' ?>&alerta=1">
                    <?= $qtdAlertas ?> alerta<?= $qtdAlertas===1?'':'s' ?> de estoque
                </a>
                <?php endif; ?>
                <span class="badge badge-info" style="margin-left:6px"><?= e(perfil_label($perfil)) ?></span>
            </div>
        </header>

        <?php if ($f = get_flash()): ?>
            <div class="toast toast-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
        <?php endif; ?>
        <div class="content">
