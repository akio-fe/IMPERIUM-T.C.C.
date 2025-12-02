/*
 ============================================================
 SCHEMA DO BANCO DE DADOS IMPERIUM E-COMMERCE
 ============================================================
 
 Arquivo: IMPERIUM_COMENTADO-V6.sql
 Versão: 6.0
 Data: 2025
 
 Propósito: 
 Este arquivo contém o schema completo do banco de dados para o sistema
 de e-commerce IMPERIUM, incluindo:
 - Estrutura de todas as tabelas (13 tabelas)
 - Relacionamentos e constraints (chaves estrangeiras)
 - Índices únicos para garantir integridade
 - Dados iniciais (seed data) para desenvolvimento/teste
 
 Funcionalidades cobertas:
 - Autenticação de usuários (Firebase UID)
 - Catálogo de produtos com categorização
 - Carrinho de compras
 - Lista de favoritos
 - Gestão de pedidos e pagamentos
 - Controle de estoque
 - Endereços de entrega
 - Controle financeiro (caixa)
 
 Charset: UTF-8 (utf8mb4) para suporte completo a caracteres especiais
 Engine: InnoDB (padrão MySQL/MariaDB) para transações ACID
 Observação: collations utf8mb4_0900_* exigem MySQL 8.0+; em MariaDB use collations equivalentes.
 
 ============================================================
*/

/*
 Rotina inicial: garante um ambiente limpo e recria o banco do zero para evitar objetos órfãos.
 Remove o banco existente (se houver) para eliminar qualquer resíduo de versões anteriores.
 */
DROP DATABASE IF EXISTS imperium;
/*
 Criação do banco de dados imperium com charset padrão UTF-8 (utf8mb4) para suportar:
 - Acentuação portuguesa (á, ã, ç, etc)
 - Caracteres especiais
 - Emojis (importante para nomes de produtos modernos)
 - Collation padrão do MySQL 8.0+
 */
CREATE DATABASE imperium;

/*
 Define imperium como banco ativo para todas as operações subsequentes.
 Todas as tabelas, índices e constraints serão criadas neste banco.
 */
USE imperium;
/*
 Tabela: Usuario
 Propósito: Armazena as informações de todos os usuários cadastrados no sistema, sejam clientes ou administradores.
 */
CREATE TABLE Usuario (
    -- Chave primária da tabela, identificador único para cada usuário (auto-incrementado)
    UsuId INT PRIMARY KEY AUTO_INCREMENT,
    -- UID (User ID) fornecido por um serviço de autenticação externo (ex: Firebase, Auth0)
    -- Esta é a forma moderna e segura de identificar um usuário, sem armazenar senhas no banco
    -- UNIQUE garante que cada conta Firebase tenha apenas um registro no sistema
    UsuUID VARCHAR(255) UNIQUE NOT NULL,
    -- E-mail do usuário, usado para login e comunicação. Deve ser único no sistema
    -- Validação de unicidade evita contas duplicadas
    UsuEmail VARCHAR(150) UNIQUE NOT NULL,
    -- Nome completo do usuário para exibição na interface e documentos
    UsuNome VARCHAR(255) NOT NULL,
    -- CPF formatado (XXX.XXX.XXX-XX) ou apenas dígitos
    -- NULL permite cadastro inicial sem documento, completado posteriormente
    -- UNIQUE evita que múltiplos usuários compartilhem o mesmo CPF
    UsuCpf VARCHAR(14) UNIQUE NULL,
    -- Telefone com DDD no formato (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
    -- NULL permite cadastro sem telefone, preenchido posteriormente
    UsuTel VARCHAR(15) UNIQUE NULL,
    -- Observação: em MySQL, índices UNIQUE permitem múltiplos valores NULL;
    -- deste modo, vários usuários podem ter telefone NULL sem violar unicidade
    -- Data de nascimento para cálculo de idade e validações legais (maioridade)
    UsuDataNasc DATE NULL,
    -- Define o papel do usuário no sistema (RBAC - Role Based Access Control)
    -- CLIENTE: acesso à loja e área pessoal
    -- FUNCIONARIO: acesso ao painel administrativo básico
    -- GERENTE: acesso completo ao sistema
    -- Padrão é CLIENTE para novos cadastros via site
    UsuFuncao ENUM('CLIENTE', 'FUNCIONARIO', 'GERENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'CLIENTE'
);
/*
 Tabela: Funcionario
 Propósito: Extensão da tabela Usuario para armazenar dados específicos de funcionários.
 
 Padrão de Design: Herança por Tabela Separada (Class Table Inheritance)
 - UsuId serve como chave primária E estrangeira simultaneamente
 - Relacionamento 1:1 com Usuario (um funcionário é sempre um usuário)
 - Apenas usuários com UsuFuncao = 'FUNCIONARIO' ou 'GERENTE' devem ter registro aqui
 - Separa dados de RH (salário, admissão) dos dados gerais do usuário
 
 Regra de Negócio:
 A criação de um registro em Funcionario deve ser feita APÓS a criação do Usuario,
 e o campo UsuFuncao do Usuario deve ser atualizado para 'FUNCIONARIO' ou 'GERENTE'.
 */
CREATE TABLE Funcionario (
    -- Chave primária e estrangeira simultaneamente, garante relação 1:1 com Usuario
    -- Este design evita a necessidade de um ID separado para funcionários
    UsuId INT PRIMARY KEY,
    
    -- Salário mensal do funcionário com precisão de centavos
    -- DECIMAL(10,2) permite valores até 99.999.999,99
    -- Usado para cálculos de folha de pagamento, décimo terceiro, férias
    FunSalario DECIMAL(10, 2) NOT NULL,
    
    -- Data de admissão para cálculo de:
    -- - Período de experiência (geralmente 90 dias)
    -- - Férias (após 12 meses)
    -- - Tempo de serviço para benefícios
    -- - Aviso prévio proporcional
    FunDataAdmissao DATE NOT NULL,
    
    -- Cargo ou função exercida no organograma da empresa
    -- Exemplos: 'Vendedor', 'Gerente de Loja', 'Estoquista', 'Analista de Marketing'
    -- Usado para definir permissões e responsabilidades no sistema
    FunCargo VARCHAR(100) NOT NULL,
    
    -- Garante integridade referencial: todo funcionário deve ser um usuário válido
    -- Sem ON DELETE: impede exclusão acidental de usuários que são funcionários
    -- Para demitir um funcionário, primeiro remova o registro aqui, depois atualize UsuFuncao
    FOREIGN KEY (UsuId) REFERENCES Usuario(UsuId)
);
/*
 Tabela: CatRoupa (Categoria da Roupa)
 Propósito: Define as categorias para classificar as roupas, facilitando a busca e organização dos produtos no site.
 */
CREATE TABLE CatRoupa (
    -- Chave primária da tabela, identificador único para cada categoria.
    CatRId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Gênero ao qual a categoria se aplica (ex: 'Masculino', 'Feminino', 'Unissex').
    CatRSexo VARCHAR(50) NOT NULL,
    -- Tipo de peça de roupa (ex: 'Camiseta', 'Calça', 'Jaqueta').
    CatRTipo VARCHAR(100) NOT NULL,
    -- Seção ou coleção a que pertence (ex: 'Verão 2024', 'Esportiva', 'Casual').
    CatRSessao VARCHAR(100) NOT NULL
);
/*
 Tabela: Roupa
 Propósito: Tabela central do e-commerce, armazena os detalhes de cada produto (roupa) disponível para venda.
 */
CREATE TABLE Roupa (
    -- Chave primária da tabela, identificador único para cada produto
    RoupaId INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    -- Nome comercial do produto exibido no site (ex: 'Camiseta Gola V')
    RoupaNome VARCHAR(100) NOT NULL,
    -- Caminho relativo ou absoluto para o arquivo 3D (GLB/GLTF)
    -- Usado pelo visualizador Three.js para renderização 3D interativa
    RoupaModelUrl VARCHAR(255) NOT NULL,
    -- URL da imagem 2D (thumbnail) para listagens, cards e preview rápido
    -- UNIQUE evita que duas roupas usem a mesma imagem (integridade visual)
    RoupaImgUrl VARCHAR(255) UNIQUE NOT NULL,
    -- Preço de venda em reais com precisão de centavos
    -- Usado para cálculo de totais, descontos e relatórios
    RoupaValor DECIMAL(10, 2) NOT NULL,
    -- Chave estrangeira que associa a roupa a uma categoria (calçados, camisas, etc)
    CatRId INT NOT NULL,
    -- Índice único composto: previne duplicação acidental de produtos
    -- Mesmo nome + mesmo modelo 3D + mesma imagem = produto duplicado
    -- Útil para evitar inserções repetidas em scripts de migração/seed
    UNIQUE INDEX idx_roupa_nome_modelo (RoupaNome, RoupaModelUrl, RoupaImgUrl)
);
/*
 Relacionamento: Roupa N:1 CatRoupa
 Garante que toda roupa pertença a uma categoria válida existente.
 Sem ON DELETE: categorias com produtos não podem ser excluídas (proteção de dados).
 */
ALTER TABLE Roupa
ADD CONSTRAINT FK_Roupa_4 FOREIGN KEY (CatRId) REFERENCES CatRoupa (CatRId);
/*
 Tabela: Carrinho
 Propósito: Armazena os itens do carrinho de compras de cada usuário.
 
 Modelo de Dados:
 - Cada linha representa um item único no carrinho (produto + quantidade + tamanho)
 - Relacionamento direto com Usuario e Roupa (modelo simplificado)
 - Não há tabela de cabeçalho separada: cada item pertence diretamente ao usuário
 - Preços são buscados em tempo real da tabela Roupa (não congelados no carrinho)
 
 Regras de Negócio:
 - Um usuário pode ter o mesmo produto múltiplas vezes com tamanhos diferentes
 - Quantidade mínima: 1 (remoção do item = DELETE do registro)
 - CarDataAtu é atualizado sempre que quantidade/tamanho mudam
 - Índice único composto (UsuId, RoupaId, CarTam) previne duplicatas
 */
CREATE TABLE Carrinho (
    -- Chave primária: identificador único de cada item no carrinho
    CarId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Data e hora de criação do item no carrinho
    -- Importante para análises de carrinhos abandonados e remarketing
    CarDataCre DATETIME NOT NULL,
    -- Data e hora da última modificação (alteração de quantidade/tamanho)
    -- Atualizado pela aplicação sempre que o item é modificado
    CarDataAtu DATETIME NOT NULL,
    -- Quantidade do produto neste item do carrinho
    -- Sempre >= 1 (remoção do item = DELETE do registro)
    CarQtd INT NOT NULL,
    -- Tamanho selecionado para este item (PP a XGG ou 34 a 43)
    -- Obrigatório; para produtos sem variação, usar valor padrão (ex: 'U')
    CarTam VARCHAR(5) NOT NULL,
    -- Usuário dono deste item do carrinho
    UsuId INT NOT NULL,
    -- Produto que está sendo adicionado ao carrinho
    RoupaId INT NOT NULL,
    -- Índice único composto: impede que o mesmo produto com o mesmo tamanho
    -- seja adicionado múltiplas vezes pelo mesmo usuário
    -- Força atualização de quantidade via UPDATE ao invés de INSERT duplicado
    UNIQUE INDEX idx_carrinho_usuario_produto_tamanho (UsuId, RoupaId, CarTam)
);
/*
 Relacionamento: Carrinho N:1 Usuario
 Cada item do carrinho pertence a um único usuário.
 Sem ON DELETE: se o usuário for excluído, o carrinho deve ser tratado antes.
 */
ALTER TABLE Carrinho
ADD CONSTRAINT FK_Carrinho_1 FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Relacionamento: Carrinho N:1 Roupa
 Cada item referencia um produto válido do catálogo.
 Sem ON DELETE: produtos no carrinho não podem ser removidos do sistema.
 */
ALTER TABLE Carrinho
ADD CONSTRAINT FK_Carrinho_2 FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId);
/*
 Tabela: Favorito
 Propósito: Armazena os produtos favoritados por cada usuário.
 
 Modelo de Dados:
 - Cada linha representa um produto favoritado por um usuário
 - Relacionamento direto com Usuario e Roupa (modelo simplificado)
 - Não há tabela de cabeçalho separada: cada item pertence diretamente ao usuário
 - Estrutura similar à tabela Carrinho, porém sem quantidade/tamanho
 
 Regras de Negócio:
 - Um usuário não pode favoritar o mesmo produto múltiplas vezes
 - Índice único composto (UsuId, RoupaId) previne duplicatas
 - FavProData registra quando o produto foi favoritado
 */
CREATE TABLE Favorito (
    -- Chave primária: identificador único de cada favorito
    FavProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Data e hora em que o produto foi favoritado
    FavProData DATETIME NOT NULL,
    -- Usuário que favoritou o produto
    UsuId INT NOT NULL,
    -- Produto que foi favoritado
    RoupaId INT NOT NULL,
    -- Índice único composto: impede que o mesmo produto seja favoritado múltiplas vezes pelo mesmo usuário
    UNIQUE INDEX idx_fav_usuario_produto (UsuId, RoupaId)
);
/*
 Relacionamento: Favorito N:1 Usuario
 Cada favorito pertence a um único usuário.
 Sem ON DELETE: se o usuário for excluído, os favoritos devem ser tratados antes.
 */
ALTER TABLE Favorito
ADD CONSTRAINT FK_Favorito_Usuario FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Relacionamento: Favorito N:1 Roupa
 Cada favorito referencia um produto válido.
 Sem ON DELETE: produtos favoritados não podem ser removidos do catálogo.
 */
ALTER TABLE Favorito
ADD CONSTRAINT FK_Favorito_Roupa FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId);
/*
 Tabela: EnderecoEntrega
 Propósito: Armazena múltiplos endereços de entrega cadastrados por cada usuário.
 Relacionamento N:1 com Usuario (um usuário pode ter vários endereços).
 Usado no checkout para seleção do local de entrega do pedido.
 */
CREATE TABLE EnderecoEntrega (
    -- Chave primária: identificador único de cada endereço
    EndEntId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Apelido/referência do endereço para identificação rápida
    -- Ex: "Casa", "Trabalho", "Casa dos Pais", "Escritório"
    EndEntRef VARCHAR(50) NOT NULL,
    -- Logradouro completo (rua, avenida, travessa, etc)
    EndEntRua VARCHAR(150) NOT NULL,
    -- CEP no formato XXXXX-XXX ou apenas dígitos
    -- Usado para validação e cálculo de frete via API dos Correios
    EndEntCep VARCHAR(9) NOT NULL,
    -- Número do imóvel (residencial ou comercial)
    EndEntNum INTEGER(7) NOT NULL,
    -- Observação: o (7) em INTEGER(7) é apenas largura de exibição;
    -- não impõe limite de 7 dígitos (o tipo INTEGER define o range)
    -- Bairro ou distrito
    EndEntBairro VARCHAR(100) NOT NULL,
    -- Cidade (município)
    EndEntCid VARCHAR(150) NOT NULL,
    -- Unidade Federativa: sigla de 2 caracteres (ex: 'SP', 'RJ', 'MG')
    EndEntEst VARCHAR(2) NOT NULL,
    -- Complemento opcional (apartamento, bloco, sala, ponto de referência)
    -- NULL permite endereços simples sem detalhes adicionais
    EndEntComple VARCHAR(100) NULL,
    -- Usuário proprietário deste endereço
    UsuId INT NOT NULL
);
/*
 Relacionamento: EnderecoEntrega N:1 Usuario
 Cada endereço pertence a um único usuário, mas um usuário pode ter vários endereços.
 Sem ON DELETE: endereços de pedidos antigos devem ser preservados para histórico.
 */
ALTER TABLE EnderecoEntrega
ADD CONSTRAINT FK_EnderecoEntrega_2 FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Tabela: Estoque
 Propósito: Gerencia os locais físicos de armazenamento dos produtos.
 
 Tipos de locais suportados:
 - Armazéns centrais (CD - Centro de Distribuição)
 - Depósitos regionais
 - Lojas físicas (estoque de ponto de venda)
 - Estoque em trânsito
 
 Funcionalidades:
 - Controle de múltiplos pontos de estoque
 - Separação geográfica para otimização de entrega
 - Base para cálculo de frete (proximidade do cliente)
 - Auditoria de localização física dos produtos
 */
CREATE TABLE Estoque (
    -- Chave primária da tabela, identificador único para cada local de estoque
    EstoId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Número ou código de identificação do local de estoque
    -- Ex: 'CD-001', 'LOJA-SP-01', 'DEP-RJ-02'
    -- Usado para etiquetagem e rastreamento interno
    EstoNum VARCHAR(15) NOT NULL,
    
    -- Estado (UF) ou nome do estado onde o estoque está localizado
    -- Ex: 'SP', 'RJ', 'MG' (ou o nome completo)
    -- Usado para cálculo de frete e ICMS
    EstoEst VARCHAR(50) NOT NULL,
    
    -- Cidade onde o estoque está localizado
    -- Ex: 'São Paulo', 'Rio de Janeiro'
    -- Usado para otimização de rotas de entrega
    EstoCid VARCHAR(50) NOT NULL,
    
    -- Logradouro completo do estoque
    EstoRua VARCHAR(150) NOT NULL,
    
    -- Bairro do estoque
    EstoBairro VARCHAR(100) NOT NULL,
    
    -- CEP do estoque no formato XXXXX-XXX
    -- Usado para cálculo preciso de distância e frete
    EstoCep VARCHAR(9) NOT NULL,
    
    -- Descrição ou nome do local de estoque
    -- Ex: 'Centro de Distribuição São Paulo', 'Loja Shopping Iguatemi'
    -- Usado para identificação amigável no sistema administrativo
    EstoDesc VARCHAR(150) NOT NULL
);
/*
 Tabela: EstoqueProduto
 Propósito: Tabela de junção (N-N) que controla a quantidade de cada produto em cada local de estoque.
 
 Modelo de Dados:
 - Relacionamento Many-to-Many entre Estoque e Roupa
 - Um produto pode estar em múltiplos estoques
 - Um estoque contém múltiplos produtos
 
 Regras de Negócio:
 - EstProQtd não pode ser negativa (validar no backend)
 - Quando EstProQtd = 0, o produto está esgotado naquele estoque
 - EstProDataAtu é atualizada automaticamente a cada UPDATE
 - Índice único impede duplicação de produto no mesmo estoque
 
 Casos de Uso:
 - Verificar disponibilidade de produto antes da venda
 - Separar pedidos do estoque mais próximo do cliente
 - Gerar relatórios de inventário por localização
 - Identificar produtos com estoque baixo (reorder point)
 */
CREATE TABLE EstoqueProduto (
    -- Chave primária da tabela
    EstProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Quantidade física do produto neste local de estoque
    -- DEFAULT 0: permite criar registro antes da entrada física do produto
    -- Regra: não pode ser negativa (validar no backend com CHECK ou trigger)
    -- Quando = 0, produto indisponível neste estoque
    EstProQtd INT NOT NULL DEFAULT 0,
    
    -- Timestamp de última atualização do registro
    -- CURRENT_DATE: define apenas a data na criação (hora = 00:00:00)
    -- ON UPDATE CURRENT_DATE: atualiza a data em qualquer UPDATE
    -- Usado para auditoria; se precisar precisão de hora, use CURRENT_TIMESTAMP
    EstProDataAtu TIMESTAMP NOT NULL DEFAULT CURRENT_DATE ON UPDATE CURRENT_DATE,
    
    -- Chave estrangeira que identifica o local de estoque
    EstoId INT NOT NULL,
    
    -- Chave estrangeira que identifica o produto (roupa)
    RoupaId INT NOT NULL,
    
    -- Índice único composto: garante que existe apenas UM registro de quantidade
    -- para cada combinação produto+estoque
    -- Impede inserções duplicadas (deve usar INSERT ... ON DUPLICATE KEY UPDATE)
    UNIQUE INDEX idx_esto_roupa (EstoId, RoupaId),
    
    -- Relacionamento com Estoque
    -- Sem ON DELETE: estoques com produtos não podem ser removidos
    FOREIGN KEY (EstoId) REFERENCES Estoque (EstoId),
    
    -- Relacionamento com Roupa
    -- Sem ON DELETE: produtos em estoque não podem ser removidos do catálogo
    FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId)
);
/*
 Tabela: Pedido
 Propósito: Armazena o cabeçalho (header) de cada pedido realizado no e-commerce.
 
 Estrutura de Dados:
 - Cabeçalho (Pedido): informações gerais da compra
 - Detalhes (PedidoProduto): itens individuais do pedido
 
 Fluxo de Status do Pedido:
 1 = Aguardando Pagamento: pedido criado, aguardando confirmação de pagamento
 2 = Pago: pagamento confirmado, aguardando separação
 3 = Em Separação: produtos sendo coletados do estoque
 4 = Enviado: pedido despachado para entrega
 5 = Entregue: pedido recebido pelo cliente
 6 = Cancelado: pedido cancelado (cliente ou sistema)
 
 Formas de Entrega (PedFormEnt):
 1 = Correios (PAC, SEDEX)
 2 = Transportadora (parceiros logísticos)
 3 = Retirar na Loja (pickup point)
 
 Formas de Pagamento:
 - Controladas na tabela Pagamento (via gateway/ID de transação)
 - Exemplos: Cartão de crédito, Boleto, PIX, Débito
 */
CREATE TABLE Pedido (
    -- Chave primária da tabela, identificador único para cada pedido
    -- Usado para rastreamento, número do pedido exibido ao cliente
    PedId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Data e hora em que o pedido foi finalizado pelo cliente
    -- Timestamp de conclusão do checkout (não do pagamento)
    -- Usado para ordenação, relatórios de vendas, SLA de entrega
    PedData DATETIME NOT NULL,
    
    -- Valor total do pedido em reais com precisão de centavos
    -- Inclui: soma dos produtos + frete + possíveis taxas
    -- Não inclui: descontos já aplicados no preço unitário
    -- DECIMAL(10,2) permite até R$ 99.999.999,99
    PedValorTotal DECIMAL(10, 2) NOT NULL,
    
    -- Código da forma de entrega selecionada no checkout
    -- 1 = Correios (PAC, SEDEX) - cálculo via API dos Correios
    -- 2 = Transportadora (Loggi, Jadlog, etc) - parcerias logísticas
    -- 3 = Retirar na Loja - cliente busca no estabelecimento físico
    -- SMALLINT(1): o (1) é largura de exibição; não limita o intervalo
    PedFormEnt SMALLINT(1) NOT NULL,
    
    -- Código do status atual do pedido (máquina de estados)
    -- 1 = Aguardando Pagamento: pedido criado, aguardando confirmação
    -- 2 = Pago: pagamento confirmado via webhook do gateway
    -- 3 = Em Separação: estoque sendo coletado (picking)
    -- 4 = Enviado: pedido despachado, código de rastreio gerado
    -- 5 = Entregue: confirmação de entrega (Correios/transportadora)
    -- 6 = Cancelado: cancelamento por cliente, fraude ou estoque insuficiente
    -- Atualizado por: webhooks de pagamento, sistema de estoque, rastreamento
    PedStatus SMALLINT(1) NOT NULL,
    
    -- Chave estrangeira que identifica o usuário que realizou a compra
    -- Usado para: histórico de pedidos, remarketing, análise de comportamento
    UsuId INT NOT NULL,
    
    -- Chave estrangeira do endereço de entrega escolhido
    -- Referência direta a EnderecoEntrega (não há snapshot dos campos)
    -- Para imutabilidade histórica, copie os dados do endereço no pedido
    EndEntId INT NOT NULL,
    
    -- Relacionamento com Usuario
    -- Sem ON DELETE: histórico de pedidos deve ser preservado mesmo se usuário for inativado
    FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId),
    
    -- Relacionamento com EnderecoEntrega
    -- Sem ON DELETE: endereços de pedidos antigos não podem ser excluídos
    FOREIGN KEY (EndEntId) REFERENCES EnderecoEntrega (EndEntId)
);
/*
 Tabela: PedidoProduto
 Propósito: Tabela de associação N-N entre Pedido e Roupa (itens do pedido).
 
 Padrão de Snapshot:
 - Armazena o preço unitário NO MOMENTO DA COMPRA (PedProPrecoUnitario)
 - Isso garante integridade histórica: alterações futuras no preço do produto
   não afetam pedidos já realizados
 - Permite calcular o valor total do pedido: SUM(PedProQtd * PedProPrecoUnitario)
 
 Estrutura:
 - Um pedido pode ter N itens (produtos)
 - Um produto pode aparecer em N pedidos
 - Cada linha representa um item específico dentro de um pedido
 
 Casos de Uso:
 - Exibir detalhes do pedido no histórico do cliente
 - Gerar nota fiscal (lista de produtos, quantidades, valores)
 - Relatórios de produtos mais vendidos
 - Cálculo de comissões de vendas
 */
CREATE TABLE PedidoProduto (
    -- Chave primária da tabela
    PedProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Quantidade deste produto específico neste pedido
    -- Sempre >= 1 (se quantidade = 0, o item não deveria existir)
    -- Ex: cliente comprou 2 unidades da mesma camiseta
    PedProQtd INT NOT NULL,
    
    -- Tamanho selecionado para este item (PP, P, M, G, GG)
    -- Importante para separação no estoque
    -- VARCHAR(5) permite variações como 'XL', 'XXL'
    PedProTam VARCHAR(5) NOT NULL,
    
    -- Preço unitário do produto NO MOMENTO DA COMPRA (snapshot)
    -- NÃO é uma referência dinâmica ao preço atual em Roupa.RoupaValor
    -- Garante que mudanças futuras de preço não alterem o valor do pedido
    -- Usado no cálculo: valor total do item = PedProQtd * PedProPrecoUnitario
    PedProPrecoUnitario DECIMAL(10, 2) NOT NULL,
    
    -- Chave estrangeira que liga este item ao pedido pai (cabeçalho)
    PedId INT NOT NULL,
    
    -- Chave estrangeira que identifica qual produto foi comprado
    -- Referencia Roupa para: nome, imagem, categoria (dados atuais)
    RoupaId INT NOT NULL,
    
    -- Relacionamento com Pedido
    -- Sem ON DELETE CASCADE explícito; exclusões do pedido devem tratar os itens
    FOREIGN KEY (PedId) REFERENCES Pedido (PedId),
    
    -- Relacionamento com Roupa
    -- Sem ON DELETE: produtos em pedidos históricos não podem ser excluídos do catálogo
    FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId)
);
/*
 Tabela: Pagamento
 Propósito: Registra as transações de pagamento associadas a um pedido.
 
 Casos de Uso:
 - Múltiplos pagamentos por pedido (ex: pagamento parcelado, vale-presente + cartão)
 - Histórico de tentativas de pagamento (aprovadas e recusadas)
 - Conciliação financeira com gateway de pagamento
 - Estornos e reembolsos
 
 Integração com Gateways:
 - PagTransacaoCod armazena o ID retornado pelo gateway (Mercado Pago, Stripe, etc)
 - Usado para consultar status, fazer estornos, emitir comprovantes
 - Webhooks do gateway atualizam PagDataHora quando pagamento é confirmado
 
 Fluxo:
 1. Cliente finaliza pedido: registrar pagamento após aprovação OU criar tentativa com PagTransacaoCod NULL e PagDataHora inicial (a ser atualizada)
 2. Gateway processa pagamento: retorna código de transação
 3. Webhook confirma pagamento: atualiza PagDataHora (confirmação) e PagTransacaoCod
 4. Sistema atualiza Pedido.PedStatus para 'Pago'
 */
CREATE TABLE Pagamento (
    -- Chave primária da tabela, identificador único para cada transação
    PagId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Data e hora em que o pagamento foi CONFIRMADO pelo gateway
    -- Diferente de Pedido.PedData (criação do pedido)
    -- Atualizado via webhook quando pagamento é aprovado
    -- Para boleto: data do pagamento, não da geração
    -- Para PIX: timestamp da confirmação
    PagDataHora DATETIME NOT NULL,
    
    -- Valor efetivamente pago nesta transação
    -- Pode ser diferente de Pedido.PedValorTotal em casos de:
    -- - Pagamento parcial (vale-presente + cartão)
    -- - Estorno parcial
    -- - Desconto aplicado no gateway
    PagValor DECIMAL(10, 2) NOT NULL,
    
    -- Código de transação retornado pelo gateway de pagamento
    -- Ex: 'mp_txn_abc123def456' (Mercado Pago)
    -- Ex: 'pi_1234567890abcdef' (Stripe)
    -- NULL: pagamento ainda não processado ou pendente
    -- Usado para: consulta de status, estornos, comprovantes, conciliação
    PagTransacaoCod VARCHAR(255) NULL,
    
    -- Chave estrangeira que liga o pagamento ao pedido correspondente
    -- Relacionamento N:1 (múltiplos pagamentos podem pertencer ao mesmo pedido)
    PedId INT NOT NULL
);
/*Criação da chave estrangeira entre as tabelas Pedido e Pagamento*/
ALTER TABLE Pagamento
ADD CONSTRAINT FK_Pagamento_1 FOREIGN KEY (PedId) REFERENCES Pedido (PedId);
/*
 Tabela: CaixaMovimento
 Propósito: Registra todas as entradas e saídas de dinheiro no caixa, para controle financeiro e auditoria.
 
 Exemplos de ENTRADA:
 - Vendas em dinheiro ou cartão
 - Depósitos bancários
 - Aporte de capital
 - Recebimento de boletos
 
 Exemplos de SAIDA:
 - Pagamento a fornecedores
 - Despesas operacionais (aluguel, luz, água)
 - Retiradas de sócios
 - Salários de funcionários
 - Impostos
 
 Funcionalidades:
 - Fluxo de caixa diário
 - Relatórios gerenciais (DRE - Demonstrativo de Resultado do Exercício)
 - Auditoria: quem fez o movimento e quando
 - Conciliação bancária
 - Controle de sangria (retirada de dinheiro do caixa físico)
 */
CREATE TABLE CaixaMovimento (
    -- Chave primária da tabela, identificador único para cada movimento
    CaxMovId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    
    -- Data e hora em que o movimento foi registrado
    -- Usado para: relatórios diários, fluxo de caixa mensal, fechamento de caixa
    CaxMovDataHora DATETIME NOT NULL,
    
    -- Tipo de movimento financeiro
    -- ENTRADA: dinheiro entra no caixa (vendas, recebimentos)
    -- SAIDA: dinheiro sai do caixa (despesas, pagamentos)
    -- ENUM garante que apenas estes dois valores são permitidos
    CaxMovTipo ENUM('ENTRADA', 'SAIDA') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    
    -- Valor monetário do movimento em reais
    -- Sempre positivo (o tipo ENTRADA/SAIDA define a direção)
    -- DECIMAL(10,2) permite até R$ 99.999.999,99
    CaxMovValor DECIMAL(10, 2) NOT NULL,
    
    -- Descrição ou motivo do movimento para auditoria
    -- Ex ENTRADA: 'Venda online pedido #1234', 'Depósito bancário'
    -- Ex SAIDA: 'Pagamento fornecedor XYZ', 'Aluguel mês 12/2024', 'Salário funcionário'
    -- Campo obrigatório para rastreabilidade
    CaxMovDescricao VARCHAR(255) NOT NULL,
    
    -- Chave estrangeira que identifica o funcionário responsável pelo movimento
    -- Deve referenciar um Usuario com UsuFuncao = 'FUNCIONARIO' ou 'GERENTE'
    -- Usado para: auditoria, identificar quem fez sangrias, responsabilização
    UsuId INT NOT NULL,
    -- Chave estrangeira: quem registrou o movimento de caixa
    -- Relacionamento N:1 com Usuario; não há ON DELETE CASCADE
    -- Exclusão de usuário deve ser bloqueada ou tratada para preservar histórico
    CONSTRAINT FK_CaixaMovimento_Usuario FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId)
);
