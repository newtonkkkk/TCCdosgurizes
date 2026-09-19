<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

// KPIs mais úteis para a oficina
$totItensEstoque = (int)$pdo->query("SELECT COALESCE(SUM(estoque_atual),0) FROM produtos WHERE ativo=1")->fetchColumn();
$valorEstoque = (float)$pdo->query("SELECT COALESCE(SUM(estoque_atual * preco_custo),0) FROM produtos WHERE ativo=1")->fetchColumn();
$totOSAbertas = (int)$pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status IN ('aberta','em_andamento','aguardando_peca')")->fetchColumn();
$faturamentoOs = (float)$pdo->query("
    SELECT COALESCE(SUM(valor_total),0) FROM ordens_servico
    WHERE status = 'concluida'
      AND YEAR(COALESCE(data_conclusao, data_abertura)) = YEAR(CURDATE())
      AND MONTH(COALESCE(data_conclusao, data_abertura)) = MONTH(CURDATE())
")->fetchColumn();
$faturamentoVendas = 0.0;
try {
    $faturamentoVendas = (float)$pdo->query("
        SELECT COALESCE(SUM(valor_total),0) FROM vendas_avulsas
        WHERE YEAR(criado_em) = YEAR(CURDATE())
          AND MONTH(criado_em) = MONTH(CURDATE())
    ")->fetchColumn();
} catch (Throwable $e) {
    $faturamentoVendas = 0.0;
}
$faturamentoMes = $faturamentoOs + $faturamentoVendas;
$agendaHoje = (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE DATE(data_hora)=CURDATE() AND status NOT IN ('cancelado','faltou')")->fetchColumn();

$osRecentes = $pdo->query("
    SELECT o.*, c.nome AS cliente
    FROM ordens_servico o
    LEFT JOIN clientes c ON c.id = o.cliente_id
    ORDER BY o.id DESC LIMIT 6
")->fetchAll();

$movs = $pdo->query("
    SELECT m.*, p.nome AS produto, p.sku
    FROM movimentacoes m
    JOIN produtos p ON p.id = m.produto_id
    ORDER BY m.id DESC LIMIT 8
")->fetchAll();

$alertas = alertas_estoque($pdo);

$pageTitle = 'Painel de Controle';
$pageHint = 'Visão geral da oficina, estoque e ordens de serviço.';
require __DIR__ . '/includes/header.php';
?>

<section class="kpis">
    <article class="kpi">
        <div class="lbl">Itens em estoque</div>
        <div class="val"><?= number_format($totItensEstoque, 0, ',', '.') ?></div>
        <div class="sub"><?= money($valorEstoque) ?> a custo</div>
    </article>
    <article class="kpi">
        <div class="lbl">O.S. em aberto</div>
        <div class="val"><?= $totOSAbertas ?></div>
        <div class="sub">abertas / andamento / peça</div>
    </article>
    <?php if (can_financeiro()): ?>
    <article class="kpi">
        <div class="lbl">Faturamento do mês</div>
        <div class="val" style="font-size:1.35rem"><?= money($faturamentoMes) ?></div>
        <div class="sub">O.S. + vendas avulsas no mês</div>
    </article>
    <?php else: ?>
    <article class="kpi">
        <div class="lbl">Alertas de estoque</div>
        <div class="val" style="color:<?= count($alertas)?'#ff6b6b':'#3dd68c' ?>"><?= count($alertas) ?></div>
        <div class="sub">abaixo do mínimo</div>
    </article>
    <?php endif; ?>
    <article class="kpi">
        <div class="lbl">Agenda de hoje</div>
        <div class="val"><?= $agendaHoje ?></div>
        <div class="sub">atendimentos previstos</div>
    </article>
</section>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Ordens de serviço recentes</h2>
            <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/pages/os.php">Ver todas</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Nº</th><th>Cliente / veículo</th><th>Status</th><th class="right">Total</th></tr>
                </thead>
                <tbody>
                <?php if (!$osRecentes): ?>
                    <tr><td colspan="4" class="empty">Nenhuma O.S. cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($osRecentes as $os): ?>
                    <tr>
                        <td class="mono"><a href="<?= e(BASE_URL) ?>/pages/os.php?ver=<?= (int)$os['id'] ?>"><?= e($os['numero']) ?></a></td>
                        <td>
                            <?= e($os['cliente'] ?? '—') ?><br>
                            <span class="muted"><?= e($os['veiculo_modelo']) ?> · <?= e($os['veiculo_placa']) ?></span>
                        </td>
                        <td><span class="badge <?= badge_os($os['status']) ?>"><?= e(status_os_label($os['status'])) ?></span></td>
                        <td class="right mono"><?= money($os['valor_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Estoque mínimo</h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(BASE_URL) ?>/pages/produtos.php?tab=estoque&alerta=1">Reposição</a>
        </div>
        <?php if (!$alertas): ?>
            <p class="empty">Nenhum item abaixo do mínimo. Bom trabalho.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Peça</th><th>Atual</th><th>Mín.</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertas as $a): ?>
                        <tr>
                            <td><?= e($a['nome']) ?></td>
                            <td class="stock-low"><?= (int)$a['estoque_atual'] ?></td>
                            <td><?= (int)$a['estoque_minimo'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card" style="margin-top:14px">
    <h2>Últimas movimentações</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Quando</th><th>Tipo</th><th>Produto</th><th>Qtd</th><th>Obs.</th></tr>
            </thead>
            <tbody>
            <?php if (!$movs): ?>
                <tr><td colspan="5" class="empty">Sem movimentações ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($movs as $m): ?>
                <tr>
                    <td class="muted"><?= date('d/m H:i', strtotime($m['criado_em'])) ?></td>
                    <td>
                        <span class="badge <?= $m['tipo']==='entrada'?'badge-ok':'badge-orange' ?>">
                            <?= $m['tipo']==='entrada'?'Entrada':'Saída' ?>
                        </span>
                    </td>
                    <td><?= e($m['produto']) ?></td>
                    <td class="mono"><?= (int)$m['quantidade'] ?></td>
                    <td class="muted"><?= e($m['observacao'] ?: ($m['nota_fiscal'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
