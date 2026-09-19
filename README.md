# Gestão de Almoxarifado Automotivo

Sistema web do TCC — Colégio Estadual João Manoel Mondrone (Medianeira-PR, 2026).

Front-end em CSS e JavaScript. Back-end em PHP (XAMPP) e MySQL.

## O que o sistema faz

- Login por seleção de perfil + senha, com logout seguro
- Perfis RBAC:
  - **Dono**: acesso total
  - **Almoxarife**: peças, entradas, saídas, inventário e fornecedores (sem financeiro)
  - **Mecânico**: O.S., agenda, clientes e **somente saída de peças para O.S.**
  - **Financeiro**: faturamento, folha, relatórios e consulta de vendas (sem alterar estoque)
- Cadastro de peças com localização, estoque mínimo e custos 
- Fornecedores 
- Entrada de materiais e atualização automática do estoque
- **Venda avulsa de peças** (fora de O.S.) com baixa de estoque e inclusão no faturamento
- Saída para O.S. ligada a mecânico + Ordem de Serviço 
- Movimentações (entrada, venda, saída O.S.) centralizadas na aba **Peças**
- Alertas visuais de estoque mínimo no painel 
- Relatórios de movimentação por produto, mecânico e O.S. 
- Agenda de atendimentos
- Ordens de Serviço (abertura, peças, mão de obra, conclusão)
- Folha de pagamento (horas + comissão sobre peças das O.S. concluídas)

## Como instalar no XAMPP

1. Copie a pasta `almoxarifado` para `C:\xampp\htdocs\almoxarifado`
2. Inicie **Apache** e **MySQL** no painel do XAMPP
3. Abra no navegador: [http://localhost/almoxarifado/install.php](http://localhost/almoxarifado/install.php)
4. Digite **RECRIAR** e clique em **Criar / recriar banco**
5. Entre em [http://localhost/almoxarifado/](http://localhost/almoxarifado/)

Se a pasta não se chamar `almoxarifado`, edite `BASE_URL` em `includes/config.php`.

## Contas de demonstração

Senha de todos: `root`

| Perfil   | Quem                         | E-mail              |
|----------|------------------------------|---------------------|
| Dono     | Matheus Valiati Turiani      | dono@oficina.com    |
| Mecânico | Jean Carlos Enrique Ribeiro  | jean@oficina.com    |
| Mecânico | Newton Zandomenighi Hauschild| newton@oficina.com  |
| Mecânico | Daniel Seixas Justen         | daniel@oficina.com  |

## Regras de negócio importantes

- **Estoque só muda por Entradas ou Saídas** (ou lançamento de peça na O.S.). Edição do cadastro de peça não altera quantidade.
- **Saída de peça exige O.S. + mecânico** .
- Lançar peça na O.S. baixa estoque, gera movimentação e atualiza valor da O.S. em transação com lock (`SELECT FOR UPDATE`).
- O.S. concluída/cancelada não aceita mais peças.

## Estrutura

```
almoxarifado/
├── index.php              login
├── dashboard.php          painel
├── logout.php
├── install.php            cria o banco
├── includes/              conexão, sessão, layout, helpers
├── pages/                 módulos
├── assets/css|js
└── sql/schema.sql
```
