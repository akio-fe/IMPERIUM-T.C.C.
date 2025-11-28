-- Active: 1755210555380@@127.0.0.1@3306@imperium
/*
################################################################################
# INSERTS PARA TESTE DO BANCO DE DADOS IMPERIUM                                #
# Os dados são inseridos na ordem de dependência das tabelas.                  #
################################################################################
*/

/*Definindo o banco de dados imperium a ser ultilizado*/
USE imperium;

/*
--------------------------------------------------------------------------------
1. Inserindo dados em tabelas sem dependências externas
   - Usuario
   - CatRoupa (Categoria da Roupa)
   - Estoque
--------------------------------------------------------------------------------
*/

-- Inserindo usuários: 1 Administrador e 2 Clientes
INSERT INTO Usuario (UsuUID, UsuEmail, UsuNome, UsuCpf, UsuTel, UsuDataNasc, UsuFuncao) VALUES
('uid_admin_001', 'admin@imperium.com', 'Admin Imperium', NULL, NULL, NULL, 1),
('uid_cliente_123', 'joao.silva@email.com', 'João da Silva', '111.222.333-44', '(11) 98765-4321', '1990-05-15', 2),
('uid_cliente_456', 'maria.oliveira@email.com', 'Maria Oliveira', '555.666.777-88', '(21) 91234-5678', '1995-10-20', 2);

-- Inserindo categorias de roupas
INSERT INTO CatRoupa (CatRSexo, CatRTipo, CatRSessao) VALUES
('Masculino', 'Camiseta', 'Casual'),
('Feminino', 'Calça', 'Esportiva'),
('Unissex', 'Jaqueta', 'Inverno 2025'),
('Masculino', 'Bermuda', 'Verão 2025');

-- Inserindo locais de estoque
INSERT INTO Estoque (EstoNum, EstoEst, EstoCid, EstoRua, EstoBairro, EstoCep, EstoDesc) VALUES
('01', 'São Paulo', 'São Paulo', 'Avenida Paulista, 1000', 'Bela Vista', '01310-100', 'Estoque Principal - SP'),
('02', 'Rio de Janeiro', 'Rio de Janeiro', 'Avenida Rio Branco, 200', 'Centro', '20040-007', 'Estoque Secundário - RJ');

/*
--------------------------------------------------------------------------------
2. Inserindo dados em tabelas com dependências
   - Roupa (depende de CatRoupa)
   - EnderecoEntrega (depende de Usuario)
   - Carrinho (depende de Usuario)
--------------------------------------------------------------------------------
*/

-- Inserindo roupas e associando-as às categorias
INSERT INTO Roupa (RoupaNome, RoupaCor, RoupaImgUrl, RoupaValor, CatRId) VALUES
('Camiseta Básica Gola V', 'Branca', '/assets/models/camiseta_branca_v.glb', 79.90, 1),
('Calça Legging Performance', 'Preta', '/assets/models/calca_legging_preta.glb', 129.90, 2),
('Jaqueta Corta-Vento Impermeável', 'Azul Marinho', '/assets/models/jaqueta_corta_vento_azul.glb', 249.90, 3),
('Camiseta Estampada Vintage', 'Cinza Mescla', '/assets/models/camiseta_estampada_cinza.glb', 99.90, 1),
('Bermuda Cargo Sarja', 'Verde Oliva', '/assets/models/bermuda_cargo_verde.glb', 149.50, 4);

-- Inserindo endereços de entrega para os usuários
-- UsuId 2 = João da Silva
INSERT INTO EnderecoEntrega (EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple, UsuId) VALUES
('Casa', 'Rua das Flores, 123', '04543-011', 123, 'Jardim Paulistano', 'São Paulo', 'SP', 'Apto 10', 2),
('Trabalho', 'Avenida Principal, 456', '04543-012', 456, 'Centro', 'São Paulo', 'SP', 'Sala 502', 2);
-- UsuId 3 = Maria Oliveira
INSERT INTO EnderecoEntrega (EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple, UsuId) VALUES
('Casa', 'Rua da Praia, 789', '22071-030', 789, 'Copacabana', 'Rio de Janeiro', 'RJ', NULL, 3);

-- Criando um carrinho de compras para o usuário João da Silva (UsuId 2)
INSERT INTO Carrinho (CarDataCre, CarDataAtu, UsuId) VALUES
(NOW(), NOW(), 2);

/*
--------------------------------------------------------------------------------
3. Inserindo dados em tabelas de junção (N-N)
   - EstoqueProduto (depende de Estoque e Roupa)
   - CarrinhoProduto (depende de Carrinho e Roupa)
--------------------------------------------------------------------------------
*/

-- Adicionando as roupas aos estoques com suas respectivas quantidades
-- Estoque 1 (SP)
INSERT INTO EstoqueProduto (EstProQtd, EstoId, RoupaId) VALUES
(100, 1, 1), -- 100 Camisetas Brancas no Estoque de SP
(50, 1, 2),  -- 50 Calças Legging no Estoque de SP
(75, 1, 4);  -- 75 Camisetas Estampadas no Estoque de SP
-- Estoque 2 (RJ)
INSERT INTO EstoqueProduto (EstProQtd, EstoId, RoupaId) VALUES
(80, 2, 3),  -- 80 Jaquetas no Estoque do RJ
(40, 2, 5);  -- 40 Bermudas no Estoque do RJ

-- Adicionando produtos ao carrinho do João da Silva (Carrinho de CarId = 1)
-- RoupaId 1 = Camiseta Branca (R$ 79.90)
INSERT INTO CarrinhoProduto (CarProQtd, CarProPreco, CarId, RoupaId) VALUES
(2, 79.90, 1, 1);
-- RoupaId 5 = Bermuda Cargo (R$ 149.50)
INSERT INTO CarrinhoProduto (CarProQtd, CarProPreco, CarId, RoupaId) VALUES
(1, 149.50, 1, 5);

/*
--------------------------------------------------------------------------------
4. Simulando um pedido finalizado e seu pagamento
   - Pedido (depende de Usuario e EnderecoEntrega)
   - PedidoProduto (depende de Pedido e Roupa)
   - Pagamento (depende de Pedido)
--------------------------------------------------------------------------------
*/

-- Criando um pedido para a usuária Maria Oliveira (UsuId 3) no endereço dela (EndEntId 3)
-- Valor Total: 1x Calça (129.90) + 1x Jaqueta (249.90) = 379.80
INSERT INTO Pedido (PedData, PedValorTotal, PedFormEnt, PedStatus, PedFormPag, UsuId, EndEntId) VALUES
('2025-08-10 14:30:00', 379.80, 1, 5, 3, 3, 3); -- 1=Correios, 5=Entregue, 3=PIX

-- Adicionando os itens ao pedido acima (PedId = 1)
-- RoupaId 2 = Calça Legging
INSERT INTO PedidoProduto (PedProQtd, PedProPrecoUnitario, PedId, RoupaId) VALUES
(1, 129.90, 1, 2);
-- RoupaId 3 = Jaqueta Corta-Vento
INSERT INTO PedidoProduto (PedProQtd, PedProPrecoUnitario, PedId, RoupaId) VALUES
(1, 249.90, 1, 3);

-- Registrando o pagamento para o pedido da Maria (PedId = 1)
INSERT INTO Pagamento (PagDataHora, PagValor, PagTransacaoCod, PedId) VALUES
('2025-08-10 14:31:10', 379.80, 'transacao_pix_xyz0987654321', 1);

/* Fim dos inserts de teste */