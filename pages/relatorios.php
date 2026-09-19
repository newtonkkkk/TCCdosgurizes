<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'almoxarife', 'financeiro']);
$pdo = db();

$ini = $_GET['ini'] ?? date('Y-m-01');
$fim = $_GET['fim'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'todos';
$produtoId = (int)($_GET['produto_id'] ?? 0);
$mecId = (int)($_GET['mecanico_id'] ?? 0);
$osId = (int)($_GET['os_id'] ?? 0);

$sql = "SELECT m.*, p.nome AS produto, p.sku, f.nome AS fornecedor, o.numero AS os_numero, u.nome AS mecanico, us.nome AS usuario
        FROM movimentacoes m
        JOIN produtos p ON p.id=m.produto_id
        LEFT JOIN fornecedores f ON f.id=m.fornecedor_id
        LEFT JOIN ordens_servico o ON o.id=m.os_id
        LEFT JOIN usuarios u ON u.id=m.mecanico_id
        LEFT JOIN usuarios us ON us.id=m.usuario_id
        WHERE DATE(m.criado_em) BETWEEN :ini AND :fim";
$params = ['ini' => $ini, 'fim' => $fim];
if (in_array($tipo, ['entrada','saida','venda'], true)) {
    $sql .= " AND m.tipo = :tipo";
    $params['tipo'] = $tipo;
}
if ($produtoId) { $sql .= " AND m.produto_id=:pid"; $params['pid']=$produtoId; }
if ($mecId) { $sql .= " AND m.mecanico_id=:mid"; $params['mid']=$mecId; }
if ($osId) { $sql .= " AND m.os_id=:oid"; $params['oid']=$osId; }
$sql .= " ORDER BY m.criado_em DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$lista = $st->fetchAll();

$produtos = $pdo->query("SELECT id,nome,sku FROM produtos ORDER BY nome")->fetchAll();
$mecs = $pdo->query("SELECT id,nome FROM usuarios WHERE perfil IN ('mecanico','dono') ORDER BY nome")->fetchAll();
$osses = $pdo->query("SELECT id,numero FROM ordens_servico ORDER BY id DESC LIMIT 80")->fetchAll();

$pageTitle = 'Relatórios de movimentação';
$pageHint = 'Extrato por produto, mecânico e O.S. (RF08).';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="card">
    <form class="toolbar" method="get">
        <div>
            <label>De</label>
            <input type="date" name="ini" value="<?= e($ini) ?>">
        </div>
        <div>
            <label>Até</label>
            <input type="date" name="fim" value="<?= e($fim) ?>">
        </div>
        <div>
            <label>Tipo</label>
            <select name="tipo">
                <option value="todos" <?= $tipo==='todos'?'selected':'' ?>>Todos</option>
                <option value="entrada" <?= $tipo==='entrada'?'selected':'' ?>>Entradas</option>
                <option value="saida" <?= $tipo==='saida'?'selected':'' ?>>Saídas (O.S.)</option>
                <option value="venda" <?= $tipo==='venda'?'selected':'' ?>>Vendas avulsas</option>
            </select>
        </div>
        <div class="grow">
            <label>Produto</label>
            <select name="produto_id">
                <option value="0">Todos</option>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $produtoId===$p['id']?'selected':'' ?>><?= e($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Mecânico</label>
            <select name="mecanico_id">
                <option value="0">Todos</option>
                <?php foreach ($mecs as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $mecId===$m['id']?'selected':'' ?>><?= e($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>O.S.</label>
            <select name="os_id">
                <option value="0">Todas</option>
                <?php foreach ($osses as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" <?= $osId===$o['id']?'selected':'' ?>><?= e($o['numero']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self:end"><button class="btn" type="submit">Filtrar</button></div>
    </form>

    <p class="muted" style="margin-bottom:10px"><?= count($lista) ?> movimento(s) no período.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Data</th><th>Tipo</th><th>Produto</th><th>Qtd</th><th>O.S.</th><th>Mecânico</th><th>Fornecedor / NF</th><th>Usuário</th></tr>
            </thead>
            <tbody>
            <?php if (!$lista): ?><tr><td colspan="8" class="empty">Nenhum registro neste filtro.</td></tr><?php endif; ?>
            <?php foreach ($lista as $m): ?>
                <tr>
                    <td class="mono"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                    <td><span class="badge <?= $m['tipo']==='entrada'?'badge-ok':($m['tipo']==='venda'?'badge-info':'badge-orange') ?>"><?= $m['tipo']==='entrada'?'Entrada':($m['tipo']==='venda'?'Venda':'Saída') ?></span></td>
                    <td><?= e($m['produto']) ?></td>
                    <td class="mono"><?= (int)$m['quantidade'] ?></td>
                    <td class="mono"><?= e($m['os_numero'] ?? '—') ?></td>
                    <td><?= e($m['mecanico'] ?? '—') ?></td>
                    <td><?= e($m['fornecedor'] ?? '—') ?><br><span class="muted"><?= e($m['nota_fiscal'] ?? '') ?></span></td>
                    <td><?= e($m['usuario'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
