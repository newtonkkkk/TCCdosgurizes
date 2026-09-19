<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'financeiro']);
$pdo = db();

/**
 * Base de comissão = soma dos subtotais de peças (os_itens) das O.S. concluídas
 * do mecânico no período. Mais confiável que só o campo valor_pecas da O.S.
 */
function base_comissao_mecanico(PDO $pdo, int $usuarioId, string $ini, string $fim): array
{
    // Peças lançadas em O.S. concluídas no período
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(i.subtotal), 0) AS base_itens
        FROM os_itens i
        INNER JOIN ordens_servico o ON o.id = i.os_id
        WHERE o.mecanico_id = ?
          AND o.status = 'concluida'
          AND DATE(COALESCE(o.data_conclusao, o.data_abertura)) BETWEEN ? AND ?
    ");
    $st->execute([$usuarioId, $ini, $fim]);
    $baseItens = (float)$st->fetchColumn();

    // Fallback: campo valor_pecas da O.S. (caso itens antigos estejam inconsistentes)
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(valor_pecas), 0)
        FROM ordens_servico
        WHERE mecanico_id = ?
          AND status = 'concluida'
          AND DATE(COALESCE(data_conclusao, data_abertura)) BETWEEN ? AND ?
    ");
    $st->execute([$usuarioId, $ini, $fim]);
    $baseCampo = (float)$st->fetchColumn();

    $base = max($baseItens, $baseCampo);

    // Detalhe das O.S. para transparência
    $st = $pdo->prepare("
        SELECT o.id, o.numero, o.valor_pecas, o.valor_total,
               COALESCE(o.data_conclusao, o.data_abertura) AS data_ref,
               (SELECT COALESCE(SUM(i.subtotal),0) FROM os_itens i WHERE i.os_id = o.id) AS pecas_itens
        FROM ordens_servico o
        WHERE o.mecanico_id = ?
          AND o.status = 'concluida'
          AND DATE(COALESCE(o.data_conclusao, o.data_abertura)) BETWEEN ? AND ?
        ORDER BY data_ref
    ");
    $st->execute([$usuarioId, $ini, $fim]);
    $osList = $st->fetchAll();

    return ['base' => $base, 'os' => $osList, 'base_itens' => $baseItens, 'base_campo' => $baseCampo];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'gerar') {
        $funcId = (int)($_POST['funcionario_id'] ?? 0);
        $ini = $_POST['periodo_inicio'] ?? '';
        $fim = $_POST['periodo_fim'] ?? '';
        $horas = (float)str_replace(',', '.', (string)($_POST['horas_trabalhadas'] ?? 0));
        $desc = (float)str_replace(',', '.', (string)($_POST['descontos'] ?? 0));
        $obs = trim($_POST['observacao'] ?? '');

        $st = $pdo->prepare("SELECT f.*, u.nome FROM funcionarios f JOIN usuarios u ON u.id=f.usuario_id WHERE f.id=? AND f.ativo=1");
        $st->execute([$funcId]);
        $f = $st->fetch();
        if (!$f || !$ini || !$fim) {
            flash('erro', 'Selecione o funcionário e o período.');
            redirect('/pages/pagamentos.php');
        }
        if ($ini > $fim) {
            flash('erro', 'A data inicial não pode ser maior que a final.');
            redirect('/pages/pagamentos.php');
        }

        $valorHoras = round($horas * (float)$f['valor_hora'], 2);
        $pct = (float)$f['comissao_pct'];
        $calc = base_comissao_mecanico($pdo, (int)$f['usuario_id'], $ini, $fim);
        $basePecas = $calc['base'];
        $comissao = round($basePecas * ($pct / 100), 2);
        $total = round($valorHoras + $comissao - $desc, 2);

        $detalhe = sprintf(
            'Peças nas O.S. concluídas: %s · Comissão %s%% = %s · %d O.S. no período',
            money($basePecas),
            number_format($pct, 2, ',', '.'),
            money($comissao),
            count($calc['os'])
        );
        if ($obs !== '') {
            $obs = $obs . ' | ' . $detalhe;
        } else {
            $obs = $detalhe;
        }

        $pdo->prepare("INSERT INTO pagamentos (funcionario_id,periodo_inicio,periodo_fim,horas_trabalhadas,valor_horas,valor_comissao,descontos,valor_total,status,observacao)
            VALUES (?,?,?,?,?,?,?,?,'pendente',?)")->execute([
            $funcId, $ini, $fim, $horas, $valorHoras, $comissao, $desc, $total, $obs
        ]);

        if ($pct <= 0) {
            flash('ok', 'Folha gerada: horas ' . money($valorHoras) . '. Este funcionário tem comissão 0% — ajuste em Perfis se necessário.');
        } elseif ($basePecas <= 0) {
            flash('ok', 'Folha gerada: horas ' . money($valorHoras) . ' + comissão R$ 0,00. Nenhuma O.S. concluída com peças no período para ' . $f['nome'] . '.');
        } else {
            flash('ok', 'Folha gerada: horas ' . money($valorHoras) . ' + comissão ' . money($comissao) . ' (sobre ' . money($basePecas) . ' em peças).');
        }
        redirect('/pages/pagamentos.php');
    }

    if ($acao === 'pagar') {
        $pdo->prepare("UPDATE pagamentos SET status='pago', data_pagamento=CURDATE() WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok', 'Pagamento marcado como pago.');
        redirect('/pages/pagamentos.php');
    }

    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare("SELECT status FROM pagamentos WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row && $row['status'] !== 'pago') {
            $pdo->prepare("DELETE FROM pagamentos WHERE id=?")->execute([$id]);
            flash('ok', 'Lançamento removido.');
        } else {
            flash('erro', 'Não é possível excluir pagamento já marcado como pago.');
        }
        redirect('/pages/pagamentos.php');
    }
}

$funcs = $pdo->query("
    SELECT f.id, f.cargo, f.valor_hora, f.comissao_pct, f.usuario_id, u.nome
    FROM funcionarios f
    JOIN usuarios u ON u.id=f.usuario_id
    WHERE f.ativo=1
    ORDER BY u.nome
")->fetchAll();

$lista = $pdo->query("
    SELECT p.*, u.nome, f.cargo, f.comissao_pct
    FROM pagamentos p
    JOIN funcionarios f ON f.id=p.funcionario_id
    JOIN usuarios u ON u.id=f.usuario_id
    ORDER BY p.id DESC
    LIMIT 50
")->fetchAll();

// Prévia opcional via GET
$previa = null;
if (!empty($_GET['previa_func']) && !empty($_GET['ini']) && !empty($_GET['fim'])) {
    $fid = (int)$_GET['previa_func'];
    foreach ($funcs as $ff) {
        if ((int)$ff['id'] === $fid) {
            $calc = base_comissao_mecanico($pdo, (int)$ff['usuario_id'], $_GET['ini'], $_GET['fim']);
            $previa = [
                'func' => $ff,
                'calc' => $calc,
                'comissao' => round($calc['base'] * ((float)$ff['comissao_pct'] / 100), 2),
                'ini' => $_GET['ini'],
                'fim' => $_GET['fim'],
            ];
            break;
        }
    }
}

$pageTitle = 'Folha e pagamentos';
$pageHint = 'Horas + comissão sobre peças das O.S. concluídas no período.';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="split">
<section class="card">
    <h2>Calcular pagamento</h2>
    <p class="muted" style="margin-bottom:12px;font-size:13px">
        A comissão é <strong>% sobre o valor das peças</strong> das O.S. com status
        <strong>Concluída</strong> no período, atribuídas a este mecânico.
        Se a comissão sair R$&nbsp;0,00: confira se existem O.S. concluídas com peças e se o % de comissão do perfil não é zero.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="gerar">
        <label>Funcionário</label>
        <select name="funcionario_id" id="func-id" required>
            <option value="">Selecione...</option>
            <?php foreach ($funcs as $f): ?>
                <option value="<?= (int)$f['id'] ?>"
                    data-pct="<?= e((string)$f['comissao_pct']) ?>"
                    <?= isset($previa) && (int)$previa['func']['id'] === (int)$f['id'] ? 'selected' : '' ?>>
                    <?= e($f['nome'].' · '.$f['cargo'].' · R$ '.number_format((float)$f['valor_hora'], 2, ',', '.').'/h · '.$f['comissao_pct'].'%') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:12px">
            <div><label>Início</label><input type="date" name="periodo_inicio" id="p-ini" required value="<?= e($previa['ini'] ?? date('Y-m-01')) ?>"></div>
            <div><label>Fim</label><input type="date" name="periodo_fim" id="p-fim" required value="<?= e($previa['fim'] ?? date('Y-m-t')) ?>"></div>
            <div><label>Horas trabalhadas</label><input name="horas_trabalhadas" value="160"></div>
            <div><label>Descontos (R$)</label><input name="descontos" value="0"></div>
            <div class="full"><label>Observação</label><textarea name="observacao"></textarea></div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Gerar cálculo</button>
            <button class="btn btn-ghost" type="button" id="btn-previa">Pré-visualizar comissão</button>
        </div>
    </form>

    <?php if ($previa): ?>
    <div style="margin-top:16px;padding:12px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)">
        <strong>Prévia — <?= e($previa['func']['nome']) ?></strong>
        <p class="muted" style="margin:6px 0">
            Período <?= e($previa['ini']) ?> a <?= e($previa['fim']) ?> ·
            Comissão <?= e((string)$previa['func']['comissao_pct']) ?>% ·
            Base em peças: <strong><?= money($previa['calc']['base']) ?></strong> ·
            Comissão estimada: <strong><?= money($previa['comissao']) ?></strong>
        </p>
        <?php if (!$previa['calc']['os']): ?>
            <p class="empty">Nenhuma O.S. concluída neste período para este mecânico.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>O.S.</th><th>Data</th><th class="right">Peças</th></tr></thead>
                    <tbody>
                    <?php foreach ($previa['calc']['os'] as $o): ?>
                        <tr>
                            <td class="mono"><a href="<?= e(BASE_URL) ?>/pages/os.php?ver=<?= (int)$o['id'] ?>"><?= e($o['numero']) ?></a></td>
                            <td class="muted"><?= date('d/m/Y', strtotime($o['data_ref'])) ?></td>
                            <td class="right mono"><?= money(max((float)$o['pecas_itens'], (float)$o['valor_pecas'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Lançamentos</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Período</th><th>Funcionário</th><th>Horas</th><th>Comissão</th><th>Total</th><th></th></tr></thead>
            <tbody>
            <?php if (!$lista): ?>
                <tr><td colspan="6" class="empty">Nenhum lançamento ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($lista as $p): ?>
                <tr>
                    <td class="mono"><?= date('d/m', strtotime($p['periodo_inicio'])) ?>–<?= date('d/m/Y', strtotime($p['periodo_fim'])) ?></td>
                    <td>
                        <?= e($p['nome']) ?><br>
                        <span class="muted"><?= e($p['cargo']) ?></span>
                        <?php if (!empty($p['observacao'])): ?>
                            <br><span class="muted" style="font-size:11px"><?= e($p['observacao']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= money($p['valor_horas']) ?></td>
                    <td class="mono"><?= money($p['valor_comissao']) ?></td>
                    <td class="mono"><strong><?= money($p['valor_total']) ?></strong></td>
                    <td>
                        <span class="badge <?= $p['status']==='pago'?'badge-ok':'badge-warn' ?>"><?= e($p['status']) ?></span>
                        <?php if ($p['status'] !== 'pago'): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="pagar">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn-link" type="submit">marcar pago</button>
                        </form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remover este lançamento?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn-link" type="submit" style="color:#ff6b6b">excluir</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<script>
document.getElementById('btn-previa')?.addEventListener('click', function () {
    const func = document.getElementById('func-id').value;
    const ini = document.getElementById('p-ini').value;
    const fim = document.getElementById('p-fim').value;
    if (!func || !ini || !fim) {
        alert('Selecione o funcionário e o período para pré-visualizar.');
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('previa_func', func);
    url.searchParams.set('ini', ini);
    url.searchParams.set('fim', fim);
    window.location.href = url.toString();
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
