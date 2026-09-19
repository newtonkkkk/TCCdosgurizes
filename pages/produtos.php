<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'almoxarife', 'mecanico', 'financeiro']);
$pdo = db();
ensure_vendas_avulsas_table($pdo);

$tab = $_GET['tab'] ?? '';
$tabsOk = ['estoque', 'entrada', 'venda', 'saida', 'historico'];
if (!in_array($tab, $tabsOk, true)) {
    $tab = '';
}
// RBAC: aba padrão e bloqueio por perfil
if (is_mecanico()) {
    // Mecânico: somente saída para O.S. (e histórico consultivo)
    if (!in_array($tab, ['saida', 'historico'], true)) {
        $tab = 'saida';
    }
} elseif (current_perfil() === 'financeiro') {
    // Financeiro: histórico/vendas (consulta), sem alterar estoque
    if (!in_array($tab, ['historico'], true)) {
        $tab = 'historico';
    }
} elseif (can_gestao_pecas()) {
    if ($tab === '') {
        $tab = 'estoque';
    }
} else {
    $tab = 'historico';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $acao = $_POST['acao'] ?? '';

    // ---- Cadastro / edição de peça ----
    if ($acao === 'salvar') {
        if (!can_gestao_pecas()) { flash('erro','Sem permissão para cadastrar peças.'); redirect('/pages/produtos.php'); }
        // cadastro
        $id = (int)($_POST['id'] ?? 0);
        $dados = [
            'nome'           => trim($_POST['nome'] ?? ''),
            'sku'            => trim($_POST['sku'] ?? ''),
            'referencia'     => trim($_POST['referencia'] ?? '') ?: null,
            'localizacao'    => trim($_POST['localizacao'] ?? '') ?: null,
            'unidade'        => trim($_POST['unidade'] ?? 'UN') ?: 'UN',
            'estoque_atual'  => (int)($_POST['estoque_atual'] ?? 0),
            'estoque_minimo' => (int)($_POST['estoque_minimo'] ?? 0),
            'preco_custo'    => (float)str_replace(',', '.', (string)($_POST['preco_custo'] ?? 0)),
            'preco_venda'    => (float)str_replace(',', '.', (string)($_POST['preco_venda'] ?? 0)),
            'ativo'          => isset($_POST['ativo']) ? 1 : 0,
        ];
        if ($dados['nome'] === '') {
            flash('erro', 'Nome da peça é obrigatório.');
        } else {
            try {
                if ($id > 0) {
                    $st = $pdo->prepare("UPDATE produtos SET nome=:nome, sku=:sku, referencia=:referencia, localizacao=:localizacao,
                        unidade=:unidade, estoque_minimo=:estoque_minimo,
                        preco_custo=:preco_custo, preco_venda=:preco_venda, ativo=:ativo WHERE id=:id");
                    $st->execute([
                        'nome' => $dados['nome'], 'sku' => $dados['sku'], 'referencia' => $dados['referencia'],
                        'localizacao' => $dados['localizacao'], 'unidade' => $dados['unidade'],
                        'estoque_minimo' => $dados['estoque_minimo'], 'preco_custo' => $dados['preco_custo'],
                        'preco_venda' => $dados['preco_venda'], 'ativo' => $dados['ativo'], 'id' => $id,
                    ]);
                    flash('ok', 'Peça atualizada. Estoque só muda por Entrada, Venda ou Saída para O.S.');
                } else {
                    $estoqueInicial = max(0, (int)$dados['estoque_atual']);
                    $dados['estoque_atual'] = 0;
                    if ($dados['sku'] === '') {
                        $dados['sku'] = proximo_sku($pdo);
                    }
                    $st = $pdo->prepare("INSERT INTO produtos (nome,sku,referencia,localizacao,unidade,estoque_atual,estoque_minimo,preco_custo,preco_venda,ativo)
                        VALUES (:nome,:sku,:referencia,:localizacao,:unidade,:estoque_atual,:estoque_minimo,:preco_custo,:preco_venda,:ativo)");
                    $st->execute($dados);
                    $novoId = (int)$pdo->lastInsertId();
                    if ($estoqueInicial > 0) {
                        registrar_entrada($pdo, $novoId, $estoqueInicial, null, '', 'Estoque inicial no cadastro', (int)current_user()['id']);
                    }
                    flash('ok', 'Peça cadastrada' . ($estoqueInicial > 0 ? ' com estoque inicial.' : '.'));
                }
            } catch (PDOException $e) {
                flash('erro', 'Não foi possível salvar a peça (dados inválidos).');
            }
        }
        redirect('/pages/produtos.php?tab=estoque');
    }

    if ($acao === 'inativar') {
        require_can(['dono', 'almoxarife']);
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE produtos SET ativo = 0 WHERE id=?")->execute([$id]);
        flash('ok', 'Peça inativada. Histórico preservado.');
        redirect('/pages/produtos.php?tab=estoque');
    }

    // ---- Entrada de estoque ----
    if ($acao === 'entrada') {
        require_can(['dono', 'almoxarife']);
        $produtoId = (int)($_POST['produto_id'] ?? 0);
        $qtd = (int)($_POST['quantidade'] ?? 0);
        $forn = (int)($_POST['fornecedor_id'] ?? 0) ?: null;
        $nf = trim($_POST['nota_fiscal'] ?? '');
        $obs = trim($_POST['observacao'] ?? '');
        $erro = registrar_entrada($pdo, $produtoId, $qtd, $forn, $nf, $obs, (int)current_user()['id']);
        flash($erro ? 'erro' : 'ok', $erro ?: 'Entrada registada e estoque atualizado.');
        redirect('/pages/produtos.php?tab=entrada');
    }

    // ---- Venda avulsa ----
    if ($acao === 'venda') {
        require_can(['dono', 'almoxarife']);
        $produtoId = (int)($_POST['produto_id'] ?? 0);
        $qtd = (int)($_POST['quantidade'] ?? 0);
        $preco = trim((string)($_POST['preco_unitario'] ?? ''));
        $precoVal = $preco === '' ? null : (float)str_replace(',', '.', $preco);
        $cliente = trim($_POST['cliente_nome'] ?? '');
        $obs = trim($_POST['observacao'] ?? '');
        $erro = registrar_venda_avulsa($pdo, $produtoId, $qtd, $precoVal, $cliente, $obs, (int)current_user()['id']);
        if ($erro) {
            flash('erro', $erro);
        } else {
            $st = $pdo->prepare("SELECT preco_venda FROM produtos WHERE id=?");
            $st->execute([$produtoId]);
            $pv = $precoVal ?? (float)$st->fetchColumn();
            flash('ok', 'Venda avulsa registada: ' . money($pv * $qtd) . ' no faturamento. Estoque baixado.');
        }
        redirect('/pages/produtos.php?tab=venda');
    }

    // ---- Saída para O.S. ----
    if ($acao === 'saida_os') {
        require_can(['dono', 'almoxarife', 'mecanico']);
        $produtoId = (int)($_POST['produto_id'] ?? 0);
        $qtd = (int)($_POST['quantidade'] ?? 0);
        $osId = (int)($_POST['os_id'] ?? 0);
        $mecId = (int)($_POST['mecanico_id'] ?? 0) ?: (int)current_user()['id'];
        $obs = trim($_POST['observacao'] ?? '');
        $erro = lancar_peca_os($pdo, $osId, $produtoId, $qtd, $mecId, $obs);
        flash($erro ? 'erro' : 'ok', $erro ?: 'Saída para O.S. registada, estoque baixado e peça lançada na ordem.');
        redirect('/pages/produtos.php?tab=saida');
    }
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM produtos WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
    $tab = 'estoque';
}

$filtroAlerta = !empty($_GET['alerta']);
$sqlLista = "SELECT * FROM produtos WHERE 1=1";
if ($filtroAlerta) {
    $sqlLista .= " AND ativo=1 AND estoque_atual <= estoque_minimo";
}
$sqlLista .= " ORDER BY ativo DESC, nome";
$lista = $pdo->query($sqlLista)->fetchAll();

$produtosAtivos = $pdo->query("SELECT id,nome,sku,estoque_atual,preco_venda FROM produtos WHERE ativo=1 ORDER BY nome")->fetchAll();
$fornecedores = $pdo->query("SELECT id,nome FROM fornecedores WHERE ativo=1 ORDER BY nome")->fetchAll();
$osList = $pdo->query("SELECT id,numero,veiculo_placa,status FROM ordens_servico WHERE status IN ('aberta','em_andamento','aguardando_peca') ORDER BY id DESC")->fetchAll();
$mecs = $pdo->query("SELECT id,nome FROM usuarios WHERE perfil IN ('mecanico','dono') AND ativo=1 ORDER BY nome")->fetchAll();

$movs = $pdo->query("
    SELECT m.*, p.nome AS produto, p.sku, f.nome AS fornecedor, o.numero AS os_numero, u.nome AS usuario
    FROM movimentacoes m
    JOIN produtos p ON p.id = m.produto_id
    LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
    LEFT JOIN ordens_servico o ON o.id = m.os_id
    LEFT JOIN usuarios u ON u.id = m.usuario_id
    ORDER BY m.id DESC
    LIMIT 80
")->fetchAll();

$vendasRecentes = [];
try {
    $vendasRecentes = $pdo->query("
        SELECT v.*, p.nome AS produto, p.sku, u.nome AS usuario
        FROM vendas_avulsas v
        JOIN produtos p ON p.id = v.produto_id
        LEFT JOIN usuarios u ON u.id = v.usuario_id
        ORDER BY v.id DESC
        LIMIT 30
    ")->fetchAll();
} catch (Throwable $e) {
    $vendasRecentes = [];
}

$pageTitle = 'Peças e estoque';
$pageHint = 'Cadastro, entradas, vendas avulsas, saídas para O.S. e histórico centralizados.';
require dirname(__DIR__) . '/includes/header.php';

function tab_url(string $t): string
{
    return e(BASE_URL) . '/pages/produtos.php?tab=' . urlencode($t);
}
?>

<nav class="card" style="margin-bottom:14px;padding:10px 12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <?php if (can_gestao_pecas()): ?>
        <a class="btn btn-sm <?= $tab==='estoque'?'':'btn-ghost' ?>" href="<?= tab_url('estoque') ?>">Estoque</a>
        <a class="btn btn-sm <?= $tab==='entrada'?'':'btn-ghost' ?>" href="<?= tab_url('entrada') ?>">Entrada</a>
        <a class="btn btn-sm <?= $tab==='venda'?'':'btn-ghost' ?>" href="<?= tab_url('venda') ?>">Venda avulsa</a>
    <?php endif; ?>
    <?php if (can_saida_os()): ?>
        <a class="btn btn-sm <?= $tab==='saida'?'':'btn-ghost' ?>" href="<?= tab_url('saida') ?>">Saída p/ O.S.</a>
    <?php endif; ?>
    <?php if (can_gestao_pecas() || can_financeiro() || can_saida_os()): ?>
        <a class="btn btn-sm <?= $tab==='historico'?'':'btn-ghost' ?>" href="<?= tab_url('historico') ?>">Histórico</a>
    <?php endif; ?>
</nav>

<?php if ($tab === 'estoque'): ?>
<div class="split">
<section class="card">
    <?php if (can_gestao_pecas()): ?>
    <h2><?= $edit ? 'Editar peça' : 'Nova peça' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Nome</label>
        <input name="nome" required value="<?= e($edit['nome'] ?? '') ?>">
        <div class="form-grid" style="margin-top:12px">
            <div><label>Referência</label><input name="referencia" value="<?= e($edit['referencia'] ?? '') ?>"></div>
            <input type="hidden" name="sku" value="<?= e($edit['sku'] ?? '') ?>">
            <div><label>Localização</label><input name="localizacao" value="<?= e($edit['localizacao'] ?? '') ?>"></div>
            <div><label>Unidade</label><input name="unidade" value="<?= e($edit['unidade'] ?? 'UN') ?>"></div>
            <div>
                <label>Estoque atual <?= $edit ? '(somente leitura)' : '(inicial)' ?></label>
                <?php if ($edit): ?>
                    <input type="number" value="<?= (int)$edit['estoque_atual'] ?>" disabled title="Altere via Entrada, Venda ou Saída">
                    <input type="hidden" name="estoque_atual" value="<?= (int)$edit['estoque_atual'] ?>">
                <?php else: ?>
                    <input type="number" name="estoque_atual" min="0" value="0">
                <?php endif; ?>
            </div>
            <div><label>Estoque mínimo</label><input type="number" name="estoque_minimo" min="0" value="<?= e((string)($edit['estoque_minimo'] ?? 0)) ?>"></div>
            <div><label>Preço custo (R$)</label><input name="preco_custo" value="<?= e((string)($edit['preco_custo'] ?? '0')) ?>"></div>
            <div><label>Preço venda (R$)</label><input name="preco_venda" value="<?= e((string)($edit['preco_venda'] ?? '0')) ?>"></div>
            <div class="full"><label><input type="checkbox" name="ativo" <?= empty($edit) || !empty($edit['ativo']) ? 'checked' : '' ?>> Ativa</label></div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Salvar</button>
            <?php if ($edit): ?><a class="btn btn-ghost" href="<?= tab_url('estoque') ?>">Cancelar</a><?php endif; ?>
        </div>
    </form>
    <?php else: ?>
    <h2>Peças</h2>
    <p class="muted">Consulta de estoque. Cadastro e entradas restritos ao dono/almoxarife.</p>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2>Inventário</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(BASE_URL) ?>/pages/produtos.php?tab=estoque&alerta=1">Só alertas</a>
    </div>
    <input type="search" placeholder="Filtrar..." data-filter-table="#tab-prod" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-prod">
            <thead>
            <tr><th>Peça</th><th>Local</th><th>Estoque</th><th>Mín.</th><th>Venda</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$lista): ?>
                <tr><td colspan="6" class="empty">Nenhuma peça encontrada.</td></tr>
            <?php endif; ?>
            <?php foreach ($lista as $p):
                $baixo = (int)$p['estoque_atual'] <= (int)$p['estoque_minimo'];
            ?>
                <tr>
                    <td><?= e($p['nome']) ?><?php if (!$p['ativo']): ?> <span class="badge badge-off">inativa</span><?php endif; ?></td>
                    <td class="muted"><?= e($p['localizacao'] ?: '—') ?></td>
                    <td class="<?= $baixo ? 'stock-low' : 'stock-ok' ?>"><?= (int)$p['estoque_atual'] ?> <?= e($p['unidade']) ?></td>
                    <td><?= (int)$p['estoque_minimo'] ?></td>
                    <td class="mono"><?= money($p['preco_venda']) ?></td>
                    <td>
                        <?php if (can_gestao_pecas()): ?>
                            <a class="btn-link" href="?tab=estoque&editar=<?= (int)$p['id'] ?>">editar</a>
                            <?php if ($p['ativo']): ?>
                            <form method="post" style="display:inline;margin-left:6px" onsubmit="return confirm('Inativar esta peça?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="inativar">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn-link" type="submit" style="color:#ff6b6b">inativar</button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php endif; ?>

<?php if ($tab === 'entrada' && can_gestao_pecas()): ?>
<section class="card">
    <h2>Entrada de materiais</h2>
    <p class="muted" style="margin-bottom:12px;font-size:13px">Compra/recebimento com NF. Atualiza o estoque e grava no histórico.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="entrada">
        <label>Peça</label>
        <select name="produto_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($produtosAtivos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['nome'].' (atual '.$p['estoque_atual'].')') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:12px">
            <div><label>Quantidade</label><input type="number" name="quantidade" min="1" value="1" required></div>
            <div>
                <label>Fornecedor</label>
                <select name="fornecedor_id">
                    <option value="">—</option>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"><?= e($f['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Nota fiscal</label><input name="nota_fiscal" placeholder="NF-..."></div>
            <div class="full"><label>Observação</label><textarea name="observacao"></textarea></div>
        </div>
        <div class="row-actions"><button class="btn" type="submit">Registrar entrada</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($tab === 'venda' && can_venda_avulsa()): ?>
<section class="card">
    <h2>Venda avulsa de peça</h2>
    <p class="muted" style="margin-bottom:12px;font-size:13px">
        Venda fora de Ordem de Serviço. Baixa o estoque e <strong>soma ao faturamento do mês</strong> no painel.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="venda">
        <label>Peça</label>
        <select name="produto_id" id="venda-produto" required>
            <option value="">Selecione...</option>
            <?php foreach ($produtosAtivos as $p): ?>
                <option value="<?= (int)$p['id'] ?>" data-preco="<?= e((string)$p['preco_venda']) ?>">
                    <?= e($p['nome'].' (disp. '.$p['estoque_atual'].' · '.money($p['preco_venda']).')') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:12px">
            <div><label>Quantidade</label><input type="number" name="quantidade" id="venda-qtd" min="1" value="1" required></div>
            <div><label>Preço unitário (R$)</label><input name="preco_unitario" id="venda-preco" placeholder="Padrão = preço de venda"></div>
            <div><label>Cliente (opcional)</label><input name="cliente_nome" placeholder="Nome de quem comprou"></div>
            <div class="full"><label>Observação</label><textarea name="observacao"></textarea></div>
        </div>
        <div class="row-actions"><button class="btn" type="submit">Registrar venda avulsa</button></div>
    </form>
</section>

<?php if ($vendasRecentes): ?>
<section class="card" style="margin-top:14px">
    <h2>Vendas avulsas recentes</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Quando</th><th>Peça</th><th>Qtd</th><th>Unit.</th><th class="right">Total</th><th>Cliente</th></tr></thead>
            <tbody>
            <?php foreach ($vendasRecentes as $v): ?>
                <tr>
                    <td class="muted"><?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?></td>
                    <td><?= e($v['produto']) ?></td>
                    <td class="mono"><?= (int)$v['quantidade'] ?></td>
                    <td class="mono"><?= money($v['preco_unitario']) ?></td>
                    <td class="right mono"><strong><?= money($v['valor_total']) ?></strong></td>
                    <td class="muted"><?= e($v['cliente_nome'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<script>
(function(){
    const sel = document.getElementById('venda-produto');
    const preco = document.getElementById('venda-preco');
    if (!sel || !preco) return;
    sel.addEventListener('change', function(){
        const opt = sel.options[sel.selectedIndex];
        const p = opt.getAttribute('data-preco');
        if (p && !preco.value) preco.placeholder = p.replace('.', ',');
    });
})();
</script>
<?php endif; ?>

<?php if ($tab === 'saida' && can_saida_os()): ?>
<section class="card">
    <h2>Saída para Ordem de Serviço</h2>
    <p class="muted" style="margin-bottom:12px;font-size:13px">
        Baixa de peça vinculada a mecânico + O.S. (RF06). Também pode ser feita na própria tela da O.S.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="saida_os">
        <label>Peça</label>
        <select name="produto_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($produtosAtivos as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['nome'].' (disp. '.$p['estoque_atual'].')') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:12px">
            <div><label>Quantidade</label><input type="number" name="quantidade" min="1" value="1" required></div>
            <div>
                <label>Mecânico responsável</label>
                <select name="mecanico_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($mecs as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= current_user()['id']==$m['id']?'selected':'' ?>><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full">
                <label>Ordem de Serviço</label>
                <select name="os_id" required>
                    <option value="">Selecione a O.S. ...</option>
                    <?php foreach ($osList as $o): ?>
                        <option value="<?= (int)$o['id'] ?>"><?= e($o['numero'].' · '.$o['veiculo_placa'].' · '.$o['status']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full"><label>Observação</label><textarea name="observacao"></textarea></div>
        </div>
        <div class="row-actions"><button class="btn" type="submit">Registrar saída para O.S.</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($tab === 'historico'): ?>
<section class="card">
    <h2>Histórico de movimentações</h2>
    <p class="muted" style="margin-bottom:12px;font-size:13px">Entradas, saídas para O.S. e vendas avulsas.</p>
    <input type="search" placeholder="Filtrar..." data-filter-table="#tab-mov" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-mov">
            <thead>
            <tr><th>Quando</th><th>Tipo</th><th>Produto</th><th>Qtd</th><th>Ref.</th><th>Obs.</th></tr>
            </thead>
            <tbody>
            <?php if (!$movs): ?>
                <tr><td colspan="6" class="empty">Sem movimentações.</td></tr>
            <?php endif; ?>
            <?php foreach ($movs as $m):
                $tipo = $m['tipo'];
                $badge = $tipo === 'entrada' ? 'badge-ok' : ($tipo === 'venda' ? 'badge-info' : 'badge-orange');
                $label = $tipo === 'entrada' ? 'Entrada' : ($tipo === 'venda' ? 'Venda' : 'Saída');
            ?>
                <tr>
                    <td class="muted"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                    <td><?= e($m['produto']) ?></td>
                    <td class="mono"><?= $tipo === 'entrada' ? '+' : '-' ?><?= (int)$m['quantidade'] ?></td>
                    <td class="muted">
                        <?php if (!empty($m['os_numero'])): ?>
                            O.S. <?= e($m['os_numero']) ?>
                        <?php elseif (!empty($m['fornecedor'])): ?>
                            <?= e($m['fornecedor']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e($m['observacao'] ?: ($m['nota_fiscal'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
