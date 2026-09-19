<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'mecanico']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente salvar de novo.');
        redirect('/pages/os.php');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'lancar_peca') {
        $osId = (int)($_POST['os_id'] ?? 0);
        $produtoId = (int)($_POST['produto_id'] ?? 0);
        $qtd = (int)($_POST['quantidade'] ?? 0);
        $mecId = (int)($_POST['mecanico_id'] ?? 0) ?: (int)(current_user()['id']);
        $obs = trim($_POST['observacao'] ?? '');

        $erro = lancar_peca_os($pdo, $osId, $produtoId, $qtd, $mecId, $obs);
        if ($erro) {
            flash('erro', $erro);
        } else {
            flash('ok', 'Peça lançada, estoque baixado e valor da O.S. atualizado.');
        }
        redirect('/pages/os.php?ver=' . $osId);
    }

    if ($acao === 'remover_peca') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $osId = (int)($_POST['os_id'] ?? 0);
        $erro = remover_peca_os($pdo, $itemId, $osId);
        if ($erro) {
            flash('erro', $erro);
        } else {
            flash('ok', 'Peça removida e estoque devolvido.');
        }
        redirect('/pages/os.php?ver=' . $osId);
    }

    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $novoNome = trim($_POST['novo_cliente'] ?? '');
        $novoTel  = trim($_POST['novo_telefone'] ?? '');

        if (!$clienteId && $novoNome !== '') {
            $pdo->prepare("INSERT INTO clientes (nome, telefone) VALUES (?,?)")->execute([$novoNome, $novoTel ?: null]);
            $clienteId = (int)$pdo->lastInsertId();
        }

        $status = $_POST['status'] ?? 'aberta';
        $permitidos = ['aberta','em_andamento','aguardando_peca','concluida','cancelada'];
        if (!in_array($status, $permitidos, true)) {
            $status = 'aberta';
        }
        $placa = strtoupper(trim($_POST['veiculo_placa'] ?? ''));
        $modelo = trim($_POST['veiculo_modelo'] ?? '');
        $mecId = (int)($_POST['mecanico_id'] ?? 0) ?: null;
        $descricao = trim($_POST['descricao'] ?? '');
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        $mao = max(0, (float)str_replace(',', '.', (string)($_POST['valor_mao_obra'] ?? 0)));
        $previsao = to_mysql_dt($_POST['data_previsao'] ?? '');

        $pecasProd = $_POST['peca_produto'] ?? [];
        $pecasQtd  = $_POST['peca_qtd'] ?? [];

        try {
            if ($id > 0) {
                $concluir = in_array($status, ['concluida','cancelada'], true) ? date('Y-m-d H:i:s') : null;
                $pdo->prepare("UPDATE ordens_servico SET cliente_id=?, veiculo_placa=?, veiculo_modelo=?, mecanico_id=?, status=?, descricao=?, diagnostico=?, valor_mao_obra=?, data_previsao=?, data_conclusao=?, valor_total = ? + valor_pecas WHERE id=?")
                    ->execute([$clienteId, $placa, $modelo, $mecId, $status, $descricao, $diagnostico, $mao, $previsao, $concluir, $mao, $id]);
                flash('ok', 'Ordem de serviço atualizada.');
                redirect('/pages/os.php?ver=' . $id);
            } else {
                $numero = proximo_numero_os($pdo);
                $pdo->prepare("INSERT INTO ordens_servico (numero,cliente_id,veiculo_placa,veiculo_modelo,mecanico_id,status,descricao,diagnostico,valor_mao_obra,valor_pecas,valor_total,data_previsao,criado_por)
                    VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?)")->execute([
                    $numero, $clienteId, $placa, $modelo, $mecId, $status, $descricao, $diagnostico, $mao, $mao, $previsao, current_user()['id']
                ]);
                $novoId = (int)$pdo->lastInsertId();

                $errosPecas = [];
                if (is_array($pecasProd)) {
                    foreach ($pecasProd as $idx => $pid) {
                        $pid = (int)$pid;
                        $q = (int)($pecasQtd[$idx] ?? 0);
                        if ($pid > 0 && $q > 0) {
                            $err = lancar_peca_os($pdo, $novoId, $pid, $q, $mecId ?: (int)current_user()['id'], 'Peça lançada na abertura da O.S.');
                            if ($err) {
                                $errosPecas[] = $err;
                            }
                        }
                    }
                }

                if ($errosPecas) {
                    flash('erro', 'O.S. ' . $numero . ' aberta, porém algumas peças não foram lançadas: ' . implode(' | ', $errosPecas));
                } else {
                    $qtdPecas = is_array($pecasProd) ? count(array_filter($pecasProd)) : 0;
                    flash('ok', 'O.S. ' . $numero . ' aberta' . ($qtdPecas ? ' com peças lançadas.' : '.'));
                }
                redirect('/pages/os.php?ver=' . $novoId);
            }
        } catch (Throwable $e) {
            flash('erro', 'Não foi possível salvar a O.S.');
            redirect('/pages/os.php');
        }
    }
}

$ver = null;
$itens = [];
if (!empty($_GET['ver'])) {
    $st = $pdo->prepare("SELECT o.*, c.nome AS cliente, c.telefone AS cliente_tel, u.nome AS mecanico
        FROM ordens_servico o
        LEFT JOIN clientes c ON c.id=o.cliente_id
        LEFT JOIN usuarios u ON u.id=o.mecanico_id
        WHERE o.id=?");
    $st->execute([(int)$_GET['ver']]);
    $ver = $st->fetch();
    if ($ver) {
        $it = $pdo->prepare("SELECT i.*, p.nome, p.sku FROM os_itens i JOIN produtos p ON p.id=i.produto_id WHERE i.os_id=? ORDER BY i.id");
        $it->execute([$ver['id']]);
        $itens = $it->fetchAll();
    }
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM ordens_servico WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}

$clientes = $pdo->query("SELECT id,nome FROM clientes ORDER BY nome")->fetchAll();
$mecs = $pdo->query("SELECT id,nome FROM usuarios WHERE perfil IN ('mecanico','dono') AND ativo=1 ORDER BY nome")->fetchAll();
$produtos = $pdo->query("SELECT id, nome, sku, estoque_atual, preco_venda FROM produtos WHERE ativo=1 ORDER BY nome")->fetchAll();
$lista = $pdo->query("
    SELECT o.*, c.nome AS cliente, u.nome AS mecanico
    FROM ordens_servico o
    LEFT JOIN clientes c ON c.id=o.cliente_id
    LEFT JOIN usuarios u ON u.id=o.mecanico_id
    ORDER BY o.id DESC
")->fetchAll();

$pageTitle = 'Ordens de Serviço';
$pageHint = 'Abertura, peças, mão de obra e conclusão da O.S.';
require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($ver): ?>
<section class="card" style="margin-bottom:14px">
    <div class="card-head">
        <h2><?= e($ver['numero']) ?> · <?= e($ver['veiculo_modelo']) ?> <?= e($ver['veiculo_placa']) ?></h2>
        <div>
            <span class="badge <?= badge_os($ver['status']) ?>"><?= e(status_os_label($ver['status'])) ?></span>
            <a class="btn btn-sm btn-ghost" href="os.php?editar=<?= (int)$ver['id'] ?>">Editar</a>
            <a class="btn btn-sm btn-ghost" href="os.php">Voltar</a>
        </div>
    </div>
    <p class="muted">Cliente: <?= e($ver['cliente'] ?? '—') ?> · <?= e($ver['cliente_tel'] ?? '') ?> · Mecânico: <?= e($ver['mecanico'] ?? '—') ?></p>
    <p style="margin:10px 0"><?= nl2br(e($ver['descricao'])) ?></p>
    <?php if ($ver['diagnostico']): ?><p><strong>Diagnóstico:</strong> <?= nl2br(e($ver['diagnostico'])) ?></p><?php endif; ?>

    <div class="table-wrap" style="margin-top:14px">
        <table>
            <thead><tr><th>Peça</th><th>Qtd</th><th>Unit.</th><th class="right">Subtotal</th><th></th></tr></thead>
            <tbody>
            <?php if (!$itens): ?>
                <tr><td colspan="5" class="empty">Nenhuma peça lançada ainda. Use o formulário abaixo.</td></tr>
            <?php endif; ?>
            <?php foreach ($itens as $i): ?>
                <tr>
                    <td><?= e($i['nome']) ?></td>
                    <td><?= (int)$i['quantidade'] ?></td>
                    <td class="mono"><?= money($i['preco_unitario']) ?></td>
                    <td class="right mono"><?= money($i['subtotal']) ?></td>
                    <td class="right">
                        <?php if (!in_array($ver['status'], ['concluida','cancelada'], true)): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remover esta peça e devolver ao estoque?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="remover_peca">
                            <input type="hidden" name="os_id" value="<?= (int)$ver['id'] ?>">
                            <input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" title="Remover">✕</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top:14px;text-align:right">
        <div class="muted">Mão de obra: <?= money($ver['valor_mao_obra']) ?></div>
        <div class="muted">Peças: <?= money($ver['valor_pecas']) ?></div>
        <div style="font-size:22px;font-weight:700">Total <?= money($ver['valor_total']) ?></div>
    </div>
</section>

<?php if (!in_array($ver['status'], ['concluida','cancelada'], true)): ?>
<section class="card" style="margin-bottom:14px">
    <h2>Lançar peça nesta O.S.</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="lancar_peca">
        <input type="hidden" name="os_id" value="<?= (int)$ver['id'] ?>">
        <div class="form-grid">
            <div class="full">
                <label>Peça</label>
                <select name="produto_id" required>
                    <option value="">Selecione a peça...</option>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= e($p['nome'] . ' (disp. ' . $p['estoque_atual'] . ' · ' . money($p['preco_venda']) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Quantidade</label>
                <input type="number" name="quantidade" min="1" value="1" required>
            </div>
            <div>
                <label>Mecânico responsável</label>
                <select name="mecanico_id">
                    <?php foreach ($mecs as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ((int)($ver['mecanico_id'] ?? 0) === (int)$m['id'] || (int)current_user()['id'] === (int)$m['id']) ? 'selected' : '' ?>>
                            <?= e($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full">
                <label>Observação</label>
                <input type="text" name="observacao" placeholder="Opcional">
            </div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Lançar peça e baixar estoque</button>
        </div>
    </form>
</section>
<?php endif; ?>
<?php endif; ?>

<div class="split">
<section class="card">
    <h2><?= $edit ? 'Editar O.S.' : 'Abrir O.S.' ?></h2>
    <form method="post" id="form-os">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Cliente cadastrado</label>
        <select name="cliente_id">
            <option value="">— novo cliente abaixo —</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['cliente_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:10px">
            <div>
                <label>Novo cliente (nome)</label>
                <input name="novo_cliente" placeholder="Se não escolher acima">
            </div>
            <div>
                <label>Telefone</label>
                <input name="novo_telefone" placeholder="(45) 9xxxx-xxxx">
            </div>
            <div>
                <label>Placa</label>
                <input name="veiculo_placa" value="<?= e($edit['veiculo_placa'] ?? '') ?>" placeholder="ABC1D23" style="text-transform:uppercase">
            </div>
            <div>
                <label>Modelo / veículo</label>
                <input name="veiculo_modelo" value="<?= e($edit['veiculo_modelo'] ?? '') ?>" placeholder="Honda Civic 2018">
            </div>
            <div>
                <label>Mecânico</label>
                <select name="mecanico_id">
                    <option value="">—</option>
                    <?php foreach ($mecs as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ((int)($edit['mecanico_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach (['aberta','em_andamento','aguardando_peca','concluida','cancelada'] as $s): ?>
                        <option value="<?= $s ?>" <?= (($edit['status'] ?? 'aberta') === $s) ? 'selected' : '' ?>><?= status_os_label($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Mão de obra (R$)</label>
                <input name="valor_mao_obra" value="<?= e((string)($edit['valor_mao_obra'] ?? '0.00')) ?>">
            </div>
            <div class="full">
                <label>Previsão</label>
                <input type="datetime-local" name="data_previsao" value="<?= !empty($edit['data_previsao']) ? date('Y-m-d\TH:i', strtotime($edit['data_previsao'])) : '' ?>">
            </div>
            <div class="full">
                <label>Descrição do serviço</label>
                <textarea name="descricao"><?= e($edit['descricao'] ?? '') ?></textarea>
            </div>
            <div class="full">
                <label>Diagnóstico</label>
                <textarea name="diagnostico"><?= e($edit['diagnostico'] ?? '') ?></textarea>
            </div>
        </div>

        <?php if (!$edit): ?>
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid #2a3140">
            <div class="card-head" style="margin-bottom:10px">
                <h3 style="margin:0;font-size:1rem">Peças desta O.S. (opcional)</h3>
                <button type="button" class="btn btn-sm btn-ghost" id="btn-add-peca">+ Peça</button>
            </div>
            <div id="lista-pecas"></div>
            <p class="muted" style="font-size:12px;margin-top:8px">As peças selecionadas serão lançadas e o estoque baixado automaticamente ao salvar a O.S.</p>
        </div>
        <?php endif; ?>

        <div class="row-actions">
            <button class="btn" type="submit"><?= $edit ? 'Salvar alterações' : 'Abrir O.S.' ?></button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Lista</h2>
    <input type="search" placeholder="Filtrar..." data-filter-table="#tab-os" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-os">
            <thead><tr><th>Nº</th><th>Cliente / veículo</th><th>Status</th><th class="right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $o): ?>
                <tr>
                    <td class="mono"><a href="?ver=<?= (int)$o['id'] ?>"><?= e($o['numero']) ?></a></td>
                    <td><?= e($o['cliente'] ?? '—') ?><br><span class="muted"><?= e($o['veiculo_modelo']) ?> · <?= e($o['veiculo_placa']) ?></span></td>
                    <td><span class="badge <?= badge_os($o['status']) ?>"><?= e(status_os_label($o['status'])) ?></span></td>
                    <td class="right mono"><?= money($o['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php if (!$edit): ?>
<script>
(function () {
    const produtos = <?= json_encode(array_map(function ($p) {
        return [
            'id' => (int)$p['id'],
            'label' => $p['nome'] . ' (disp. ' . $p['estoque_atual'] . ')',
        ];
    }, $produtos), JSON_UNESCAPED_UNICODE) ?>;

    const lista = document.getElementById('lista-pecas');
    const btn = document.getElementById('btn-add-peca');
    if (!lista || !btn) return;

    function addRow() {
        const row = document.createElement('div');
        row.className = 'form-grid';
        row.style.marginBottom = '8px';
        row.style.alignItems = 'end';

        const sel = document.createElement('select');
        sel.name = 'peca_produto[]';
        sel.innerHTML = '<option value="">Selecione...</option>' +
            produtos.map(p => '<option value="' + p.id + '">' + p.label + '</option>').join('');

        const qtd = document.createElement('input');
        qtd.type = 'number';
        qtd.name = 'peca_qtd[]';
        qtd.min = '1';
        qtd.value = '1';
        qtd.style.width = '90px';

        const rem = document.createElement('button');
        rem.type = 'button';
        rem.className = 'btn btn-sm btn-ghost';
        rem.textContent = '✕';
        rem.onclick = function () { row.remove(); };

        const d1 = document.createElement('div');
        d1.className = 'full';
        const l1 = document.createElement('label');
        l1.textContent = 'Peça';
        d1.appendChild(l1);
        d1.appendChild(sel);

        const d2 = document.createElement('div');
        const l2 = document.createElement('label');
        l2.textContent = 'Qtd';
        d2.appendChild(l2);
        d2.appendChild(qtd);

        const d3 = document.createElement('div');
        d3.appendChild(rem);

        row.appendChild(d1);
        row.appendChild(d2);
        row.appendChild(d3);
        lista.appendChild(row);
    }

    btn.addEventListener('click', addRow);
})();
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
