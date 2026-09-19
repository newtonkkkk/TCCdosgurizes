-- ============================================================
-- GESTÃO DE ALMOXARIFADO AUTOMOTIVO
-- Banco de dados MySQL para XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS almoxarifado_automotivo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE almoxarifado_automotivo;

-- ------------------------------------------------------------
-- USUÁRIOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  nif VARCHAR(20) DEFAULT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  perfil ENUM('dono','almoxarife','mecanico','financeiro') NOT NULL DEFAULT 'mecanico',
  telefone VARCHAR(30) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- FUNCIONÁRIOS (dados de folha / comissão)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcionarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  cargo VARCHAR(80) DEFAULT 'Mecânico',
  valor_hora DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  comissao_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- FORNECEDORES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fornecedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  nif VARCHAR(20) DEFAULT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  endereco VARCHAR(220) DEFAULT NULL,
  prazo_entrega INT DEFAULT 7 COMMENT 'dias',
  observacao TEXT,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CLIENTES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(160) NOT NULL,
  nif VARCHAR(20) DEFAULT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  endereco VARCHAR(220) DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUTOS / PEÇAS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produtos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(180) NOT NULL,
  sku VARCHAR(60) NOT NULL UNIQUE,
  referencia VARCHAR(80) DEFAULT NULL,
  localizacao VARCHAR(80) DEFAULT NULL,
  unidade VARCHAR(10) NOT NULL DEFAULT 'UN',
  estoque_atual INT NOT NULL DEFAULT 0,
  estoque_minimo INT NOT NULL DEFAULT 0,
  preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ORDENS DE SERVIÇO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ordens_servico (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NOT NULL UNIQUE,
  cliente_id INT UNSIGNED DEFAULT NULL,
  veiculo_placa VARCHAR(12) DEFAULT NULL,
  veiculo_modelo VARCHAR(80) DEFAULT NULL,
  mecanico_id INT UNSIGNED DEFAULT NULL,
  status ENUM('aberta','em_andamento','aguardando_peca','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  descricao TEXT,
  diagnostico TEXT,
  valor_mao_obra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_pecas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  data_abertura DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_previsao DATETIME DEFAULT NULL,
  data_conclusao DATETIME DEFAULT NULL,
  criado_por INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (mecanico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ITENS DA O.S. (peças aplicadas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS os_itens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  os_id INT UNSIGNED NOT NULL,
  produto_id INT UNSIGNED NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MOVIMENTAÇÕES DE ESTOQUE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimentacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('entrada','saida','venda') NOT NULL,
  produto_id INT UNSIGNED NOT NULL,
  quantidade INT NOT NULL,
  fornecedor_id INT UNSIGNED DEFAULT NULL,
  os_id INT UNSIGNED DEFAULT NULL,
  mecanico_id INT UNSIGNED DEFAULT NULL,
  nota_fiscal VARCHAR(60) DEFAULT NULL,
  observacao VARCHAR(255) DEFAULT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produto_id) REFERENCES produtos(id),
  FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE SET NULL,
  FOREIGN KEY (mecanico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VENDAS AVULSAS (peças fora de O.S.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vendas_avulsas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id INT UNSIGNED NOT NULL,
  quantidade INT NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cliente_nome VARCHAR(160) DEFAULT NULL,
  observacao VARCHAR(255) DEFAULT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produto_id) REFERENCES produtos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_vendas_data ON vendas_avulsas (criado_em);

-- ------------------------------------------------------------
-- AGENDA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agendamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED DEFAULT NULL,
  cliente_nome VARCHAR(160) DEFAULT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  veiculo VARCHAR(80) DEFAULT NULL,
  mecanico_id INT UNSIGNED DEFAULT NULL,
  data_hora DATETIME NOT NULL,
  servico VARCHAR(200) DEFAULT NULL,
  status ENUM('agendado','confirmado','em_atendimento','concluido','cancelado','faltou') NOT NULL DEFAULT 'agendado',
  observacao TEXT,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (mecanico_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PAGAMENTOS DE FUNCIONÁRIOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  funcionario_id INT UNSIGNED NOT NULL,
  periodo_inicio DATE NOT NULL,
  periodo_fim DATE NOT NULL,
  horas_trabalhadas DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  valor_horas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_comissao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  descontos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('pendente','pago') NOT NULL DEFAULT 'pendente',
  data_pagamento DATE DEFAULT NULL,
  observacao VARCHAR(255) DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- ÍNDICES DE DESEMPENHO
-- ------------------------------------------------------------
CREATE INDEX idx_produtos_estoque ON produtos (ativo, estoque_atual, estoque_minimo);
CREATE INDEX idx_os_status ON ordens_servico (status);
CREATE INDEX idx_os_conclusao ON ordens_servico (status, data_conclusao);
CREATE INDEX idx_mov_tipo_data ON movimentacoes (tipo, criado_em);
CREATE INDEX idx_mov_produto ON movimentacoes (produto_id);
CREATE INDEX idx_agenda_data ON agendamentos (data_hora, status);
CREATE INDEX idx_os_itens_os ON os_itens (os_id);

-- Senha padrão de TODOS: root
INSERT INTO usuarios (nome, email, nif, senha, perfil, telefone, ativo) VALUES
('Matheus Valiati Turiani', 'dono@oficina.com', '00000000000', '$2y$10$sZmoLHlwfjVv2qt8TEf7d.xsUlEjXBawa/zlvRoh1PJ2E.SFpvR4.', 'dono', '(45) 99999-0001', 1),
('Jean Carlos Enrique Ribeiro', 'jean@oficina.com', '11111111111', '$2y$10$sZmoLHlwfjVv2qt8TEf7d.xsUlEjXBawa/zlvRoh1PJ2E.SFpvR4.', 'mecanico', '(45) 99999-0002', 1),
('Newton Zandomenighi Hauschild', 'newton@oficina.com', '22222222222', '$2y$10$sZmoLHlwfjVv2qt8TEf7d.xsUlEjXBawa/zlvRoh1PJ2E.SFpvR4.', 'mecanico', '(45) 99999-0003', 1),
('Daniel Seixas Justen', 'daniel@oficina.com', '33333333333', '$2y$10$sZmoLHlwfjVv2qt8TEf7d.xsUlEjXBawa/zlvRoh1PJ2E.SFpvR4.', 'mecanico', '(45) 99999-0004', 1);

INSERT INTO funcionarios (usuario_id, cargo, valor_hora, comissao_pct) VALUES
(1, 'Dono / Gestor', 50.00, 0.00),
(2, 'Mecânico', 32.00, 8.00),
(3, 'Mecânico Sênior', 38.00, 10.00),
(4, 'Mecânico', 30.00, 7.00);

INSERT INTO fornecedores (nome, nif, telefone, email, endereco, prazo_entrega, ativo) VALUES
('AutoPeças Medianeira', '12.345.678/0001-90', '(45) 3264-1000', 'vendas@autopecasmed.com', 'Av. Internacional, 1500 - Medianeira/PR', 3, 1),
('Distribuidora Sul Peças', '98.765.432/0001-10', '(45) 3521-2000', 'contato@sulpecas.com', 'Rua das Indústrias, 220 - Cascavel/PR', 5, 1),
('Importadora MotorMax', '11.222.333/0001-44', '(41) 3333-4444', 'pedidos@motormax.com', 'Curitiba/PR', 10, 1);

INSERT INTO clientes (nome, nif, telefone, email, endereco) VALUES
('Pedro Henrique Silva', '123.456.789-00', '(45) 98888-1111', 'pedro@email.com', 'Rua das Flores, 45'),
('Maria Aparecida Costa', '987.654.321-00', '(45) 97777-2222', 'maria@email.com', 'Av. Brasil, 890'),
('José Carlos Ferreira', '456.789.123-00', '(45) 96666-3333', 'jose@email.com', 'Rua Paraná, 12');

INSERT INTO produtos (nome, sku, referencia, localizacao, unidade, estoque_atual, estoque_minimo, preco_custo, preco_venda, ativo) VALUES
('Filtro de óleo WIX 51515', 'FO-001', 'WIX-51515', 'A1-01', 'UN', 18, 8, 22.50, 45.00, 1),
('Filtro de ar esportivo KN', 'FA-002', 'KN-33-2304', 'A1-02', 'UN', 6, 5, 85.00, 149.90, 1),
('Pastilha de freio dianteira Bosch', 'PF-003', 'BB1234', 'B2-01', 'JG', 4, 6, 95.00, 189.00, 1),
('Disco de freio ventilado', 'DF-004', 'DF-278', 'B2-02', 'UN', 10, 4, 140.00, 260.00, 1),
('Óleo 5W30 sintético 1L', 'OL-005', 'MOBIL-5W30', 'C3-01', 'LT', 24, 12, 28.00, 49.90, 1),
('Vela de ignição NGK Iridium', 'VL-006', 'IZFR6K11', 'A2-03', 'UN', 16, 8, 32.00, 59.90, 1),
('Correia dentada Gates', 'CD-007', 'TCK306', 'D1-01', 'UN', 3, 4, 110.00, 210.00, 1),
('Amortecedor dianteiro Monroe', 'AM-008', 'G16780', 'E1-01', 'UN', 8, 4, 220.00, 389.00, 1),
('Bateria 60Ah Moura', 'BT-009', 'M60GD', 'F1-01', 'UN', 5, 3, 380.00, 620.00, 1),
('Lâmpada H4 Philips', 'LP-010', 'H4-12342', 'A3-01', 'UN', 20, 10, 18.00, 35.00, 1);

INSERT INTO ordens_servico (numero, cliente_id, veiculo_placa, veiculo_modelo, mecanico_id, status, descricao, valor_mao_obra, valor_pecas, valor_total, data_previsao, data_conclusao, criado_por) VALUES
('OS-2026-0001', 1, 'ABC1D23', 'Honda Civic 2018', 2, 'concluida', 'Troca de pastilhas e discos dianteiros + revisão de fluído.', 180.00, 449.00, 629.00, DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), 1),
('OS-2026-0002', 2, 'XYZ9K87', 'VW Gol 2014', 3, 'aberta', 'Troca de óleo e filtros. Verificar correia.', 90.00, 0.00, 90.00, DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, 1),
('OS-2026-0003', 3, 'QWE4R56', 'Fiat Strada 2020', 4, 'aguardando_peca', 'Substituição de amortecedores dianteiros.', 250.00, 0.00, 250.00, DATE_ADD(NOW(), INTERVAL 4 DAY), NULL, 1);

-- Peças da O.S. concluída (para demonstrar comissão na folha)
INSERT INTO os_itens (os_id, produto_id, quantidade, preco_unitario, subtotal) VALUES
(1, 3, 1, 189.00, 189.00),
(1, 4, 1, 260.00, 260.00);

INSERT INTO agendamentos (cliente_id, cliente_nome, telefone, veiculo, mecanico_id, data_hora, servico, status, observacao) VALUES
(1, 'Pedro Henrique Silva', '(45) 98888-1111', 'Honda Civic 2018', 2, DATE_ADD(NOW(), INTERVAL 3 HOUR), 'Revisão de freios', 'confirmado', NULL),
(2, 'Maria Aparecida Costa', '(45) 97777-2222', 'VW Gol 2014', 3, DATE_ADD(NOW(), INTERVAL 1 DAY), 'Troca de óleo', 'agendado', 'Cliente pediu orçamento antes'),
(3, 'José Carlos Ferreira', '(45) 96666-3333', 'Fiat Strada 2020', 4, DATE_ADD(NOW(), INTERVAL 2 DAY), 'Suspensão dianteira', 'agendado', NULL);

INSERT INTO movimentacoes (tipo, produto_id, quantidade, fornecedor_id, nota_fiscal, observacao, usuario_id) VALUES
('entrada', 1, 20, 1, 'NF-1001', 'Compra inicial filtro óleo', 2),
('entrada', 5, 30, 1, 'NF-1001', 'Compra inicial óleo', 2),
('entrada', 3, 8, 2, 'NF-2044', 'Pastilhas Bosch', 2);
