/*
 Rotina inicial: garante um ambiente limpo e recria o banco do zero para evitar objetos órfãos.
 */
DROP DATABASE IF EXISTS imperium;
/*Criação do banco de dados imperium*/
CREATE DATABASE imperium;
/*Definindo o banco de dados imperium a ser ultilizado*/
USE imperium;
/*
 Tabela: Usuario
 Propósito: Armazena as informações de todos os usuários cadastrados no sistema, sejam clientes ou administradores.
 */
CREATE TABLE Usuario (
    -- Chave primária da tabela, identificador único para cada usuário.
    UsuId INT PRIMARY KEY AUTO_INCREMENT,
    -- UID (User ID) fornecido por um serviço de autenticação externo (ex: Firebase, Auth0).
    -- Esta é a forma moderna e segura de identificar um usuário, sem armazenar senhas no banco de dados.
    UsuUID VARCHAR(255) UNIQUE NOT NULL,
    -- E-mail do usuário, usado para login e comunicação. Deve ser único.
    UsuEmail VARCHAR(150) UNIQUE NOT NULL,
    -- Nome completo do usuário.
    UsuNome VARCHAR(255) NOT NULL,
    -- O CPF e o Telefone podem ser nulos inicialmente e preenchidos depois pelo usuário.
    UsuCpf VARCHAR(14) UNIQUE NULL,
    UsuTel VARCHAR(15) UNIQUE NULL,
    UsuDataNasc DATE NULL,
    -- Define o papel do usuário no sistema. Ex: 1 = Administrador, 2 = Cliente. O padrão é ser cliente.
    UsuFuncao ENUM('CLIENTE', 'FUNCIONARIO') NOT NULL DEFAULT 'CLIENTE'
);
/* Tabelas específicas para Cliente e Funcionario */
CREATE TABLE Cliente (
    UsuId INT PRIMARY KEY,
    FOREIGN KEY (UsuId) REFERENCES Usuario(UsuId)
);
CREATE TABLE Funcionario (
    UsuId INT PRIMARY KEY,
    FunSalario DECIMAL(10, 2) NOT NULL,
    FunDataAdmissao DATE NOT NULL,
    FunCargo VARCHAR(100) NOT NULL,
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
    -- Sessão ou coleção a que pertence (ex: 'Verão 2024', 'Esportiva', 'Casual').
    CatRSessao VARCHAR(100) NOT NULL
);
/*
 Tabela: Roupa
 Propósito: Tabela central do e-commerce, armazena os detalhes de cada produto (roupa) disponível para venda.
 */
CREATE TABLE Roupa (
    -- Chave primária da tabela, identificador único para cada produto.
    RoupaId INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    -- Nome do produto (ex: 'Camiseta Gola V').
    RoupaNome VARCHAR(100) NOT NULL,
    -- Caminho para o arquivo 3D da peça (GLB/GLTF) que será carregado pelo visualizador.
    RoupaModelUrl VARCHAR(255) NOT NULL,
    -- URL da imagem 2D (thumbnail) exibida nas listagens do site.
    RoupaImgUrl VARCHAR(255) UNIQUE NOT NULL,
    -- Preço de venda do produto.
    RoupaValor DECIMAL(10, 2) NOT NULL,
    -- Chave estrangeira que liga a roupa a uma categoria.
    CatRId INT NOT NULL,
    -- Garante que a combinação de nome e arquivo do modelo 3D (mesmo item em outro deploy) seja única, evitando duplicidade.
    UNIQUE INDEX idx_roupa_nome_modelo (RoupaNome, RoupaModelUrl)
);
/* Criação da chave estrangeira entre as tabelas CatRoupa e Roupa*/
ALTER TABLE Roupa
ADD CONSTRAINT FK_Roupa_4 FOREIGN KEY (CatRId) REFERENCES CatRoupa (CatRId);
/*
 Tabela: Carrinho
 Propósito: Armazena o dados do carrinho que será criado para cada usuario e usado para agrupar os CarrinhoProdutos.
 */
CREATE TABLE Carrinho (
    -- Definindo CarId como chave primaria e dando ela auto incremento --
    CarId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Data e hora em que o carrinho foi criado. Importante para análises de carrinhos abandonados.
    CarDataCre DATETIME NOT NULL,
    -- Data e hora da última modificação no carrinho (adição/remoção de item).
    CarDataAtu DATETIME NOT NULL,
    -- Chave estrangeira que liga o carrinho a um usuário específico.
    UsuId INT NOT NULL
);
/* Criação da chave estrangeira entre as tabelas Usuario e Carrinho */
ALTER TABLE Carrinho
ADD CONSTRAINT FK_Carrinho_1 FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Tabela: CarrinhoProduto
 Propósito: Tabela de junção (N-N) que lista os produtos que um usuário adicionou ao seu carrinho de compras.
 */
CREATE TABLE CarrinhoProduto (
    -- Definindo CarProID como chave primaria e dando ela auto incremento --
    CarProID INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Quantidade do produto no carrinho.
    CarProQtd INT NOT NULL,
    -- Preço do produto no momento em que foi adicionado ao carrinho, para evitar problemas com alteração de preços.
    CarProPreco DECIMAL(10, 2) NOT NULL,
    CarId INT NOT NULL,
    RoupaId INT NOT NULL,
    -- Garante que não é possível adicionar o mesmo produto duas vezes no mesmo carrinho (deve-se apenas atualizar a quantidade).
    UNIQUE INDEX idx_car_roupa (CarId, RoupaId)
);
/*Criação da chave estrangeira entre as tabelas Carrinho e CarrinhoProduto*/
ALTER TABLE CarrinhoProduto
ADD CONSTRAINT FK_CarrinhoProduto_2 FOREIGN KEY (CarId) REFERENCES Carrinho (CarId);
/*Criação da chave estrangeira entre as tabelas Roupa e CarrinhoProduto*/
ALTER TABLE CarrinhoProduto
ADD CONSTRAINT FK_CarrinhoProduto_3 FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId);
/*
 Tabela: Favorito
 Propósito: Centraliza os favoritos de cada usuário, servindo como cabeçalho para as peças salvas.
 Mantemos datas de criação/atualização para auditoria, assim como ocorre no carrinho.
 */
CREATE TABLE Favorito (
    FavId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    FavDataCre DATETIME NOT NULL,
    FavDataAtu DATETIME NOT NULL,
    UsuId INT NOT NULL
);
/* Chave estrangeira entre Favorito e Usuario */
ALTER TABLE Favorito
ADD CONSTRAINT FK_Favorito_Usuario FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Tabela: FavoritoProduto
 Propósito: Lista as roupas marcadas como favoritas por cada usuário.
 Estrutura espelhada de CarrinhoProduto, mas sem quantidade/preço, pois favoritos não possuem esses conceitos.
 */
CREATE TABLE FavoritoProduto (
    FavProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    FavId INT NOT NULL,
    RoupaId INT NOT NULL,
    FavProData DATETIME NOT NULL,
    UNIQUE INDEX idx_fav_roupa (FavId, RoupaId)
);
/* Chaves estrangeiras entre FavoritoProduto, Favorito e Roupa */
ALTER TABLE FavoritoProduto
ADD CONSTRAINT FK_FavoritoProduto_1 FOREIGN KEY (FavId) REFERENCES Favorito (FavId);
ALTER TABLE FavoritoProduto
ADD CONSTRAINT FK_FavoritoProduto_2 FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId);
/*
 Tabela: EnderecoEntrega
 Propósito: Armazena os múltiplos endereços de entrega que um usuário pode cadastrar em sua conta.
 */
CREATE TABLE EnderecoEntrega (
    -- Chave primária da tabela, identificador único para cada endereço.
    EndEntId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Referência para o endereço, ex: "Casa", "Trabalho", para fácil identificação pelo usuário.
    EndEntRef VARCHAR(50) NOT NULL,
    EndEntRua VARCHAR(150) NOT NULL,
    EndEntCep VARCHAR(9) NOT NULL,
    EndEntNum INTEGER(7) NOT NULL,
    EndEntBairro VARCHAR(100) NOT NULL,
    EndEntCid VARCHAR(150) NOT NULL,
    -- Sigla do estado (UF) com 2 caracteres é o padrão (ex: 'SP', 'RJ').
    EndEntEst VARCHAR(2) NOT NULL,
    -- Complemento do endereço (ex: "Apto 101", "Bloco B"). Pode ser opcional.
    EndEntComple VARCHAR(100) NULL,
    -- Chave estrangeira que liga o endereço a um usuário.
    UsuId INT NOT NULL
);
/* Criação da chave estrangeira entre as tabelas Usuario e Endereço de entrega */
ALTER TABLE EnderecoEntrega
ADD CONSTRAINT FK_EnderecoEntrega_2 FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId);
/*
 Tabela: Estoque
 Propósito: Gerencia os locais físicos de armazenamento dos produtos (armazéns, depósitos, lojas físicas).
 */
CREATE TABLE Estoque (
    -- Chave primária da tabela, identificador único para cada local de estoque.
    EstoId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Número ou código de identificação do local de estoque.
    EstoNum VARCHAR(15) NOT NULL,
    EstoEst VARCHAR(50) NOT NULL,
    EstoCid VARCHAR(50) NOT NULL,
    EstoRua VARCHAR(150) NOT NULL,
    EstoBairro VARCHAR(100) NOT NULL,
    EstoCep VARCHAR(9) NOT NULL,
    -- Descrição do estoque
    EstoDesc VARCHAR(150) NOT NULL
);
/*
 Tabela: EstoqueProduto
 Propósito: Tabela de junção (N-N) que controla a quantidade de cada produto (Roupa) em cada local de estoque (Estoque).
 */
CREATE TABLE EstoqueProduto (
    -- Chave primária da tabela.
    EstProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Quantidade do produto específico neste local de estoque. Não pode ser negativa.
    EstProQtd INT NOT NULL DEFAULT 0,
    -- Data e hora da última atualização do registro de estoque. Atualiza automaticamente sempre que a linha é modificada.
    EstProDataAtu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Chave estrangeira que identifica o local de estoque.
    EstoId INT NOT NULL,
    -- Chave estrangeira que identifica o produto (roupa).
    RoupaId INT NOT NULL,
    -- Garante que existe apenas um registro de quantidade para cada produto em cada estoque.
    UNIQUE INDEX idx_esto_roupa (EstoId, RoupaId),
    FOREIGN KEY (EstoId) REFERENCES Estoque (EstoId),
    FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId)
);
/*
 Tabela: Pedido
 Propósito: Armazena o cabeçalho de cada pedido realizado, com informações gerais da compra, status e entrega.
 Os itens específicos do pedido são armazenados em uma tabela separada (PedidoProduto)
 para permitir um número ilimitado de itens por pedido.
 */
CREATE TABLE Pedido (
    -- Chave primária da tabela, identificador único para cada pedido.
    PedId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Data e hora em que o pedido foi finalizado pelo cliente.
    PedData DATETIME NOT NULL,
    -- Valor total do pedido, incluindo todos os itens e possíveis taxas.
    PedValorTotal DECIMAL(10, 2) NOT NULL,
    -- Código para a forma de entrega. 1 = Correios, 2 = Transportadora, 3 = Retirar na Loja.
    PedFormEnt SMALLINT(1) NOT NULL,
    -- Código para o status atual do pedido.1 = Aguardando Pagamento, 2 = Pago, 3 = Em Separação, 4 = Enviado, 5 = Entregue, 6 = Cancelado.
    PedStatus SMALLINT(1) NOT NULL,
    -- Código para a forma de pagamento escolhida. Ex: 1 = Cartão de Crédito, 2 = Boleto, 3 = PIX.
    PedFormPag SMALLINT(1) NOT NULL,
    -- Chave estrangeira que identifica o usuário que fez o pedido.
    UsuId INT NOT NULL,
    -- Chave estrangeira que identifica o endereço de entrega selecionado para este pedido.
    EndEntId INT NOT NULL,
    FOREIGN KEY (UsuId) REFERENCES Usuario (UsuId),
    FOREIGN KEY (EndEntId) REFERENCES EnderecoEntrega (EndEntId)
);
/* Tabela de associação para os itens de um pedido (relação N-N entre Pedido e Roupa) */
CREATE TABLE PedidoProduto (
    -- Chave primária da tabela.
    PedProId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Quantidade do produto específico neste pedido.
    PedProQtd INT NOT NULL,
    -- Preço unitário do produto no momento da compra, para garantir a integridade histórica do pedido.
    PedProPrecoUnitario DECIMAL(10, 2) NOT NULL,
    -- Chave estrangeira que liga este item ao pedido correspondente.
    PedId INT NOT NULL,
    -- Chave estrangeira que identifica o produto comprado.
    RoupaId INT NOT NULL,
    FOREIGN KEY (PedId) REFERENCES Pedido (PedId),
    FOREIGN KEY (RoupaId) REFERENCES Roupa (RoupaId)
);
/*
 Tabela: Pagamento
 Propósito: Registra as transações de pagamento associadas a um pedido. Permite múltiplos pagamentos por pedido, se necessário.
 */
CREATE TABLE Pagamento (
    -- Chave primária da tabela, identificador único para cada transação de pagamento.
    PagId INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    -- Data e hora em que o pagamento foi processado.
    PagDataHora DATETIME NOT NULL,
    -- Valor efetivamente pago nesta transação.
    PagValor DECIMAL(10, 2) NOT NULL,
    -- Código de transação retornado pelo gateway de pagamento, se houver.
    PagTransacaoCod VARCHAR(255) NULL,
    -- Chave estrangeira que liga o pagamento ao pedido.
    PedId INT NOT NULL
);
/*Criação da chave estrangeira entre as tabelas Pedido e Pagamento*/
ALTER TABLE Pagamento
ADD CONSTRAINT FK_Pagamento_1 FOREIGN KEY (PedId) REFERENCES Pedido (PedId);
/*
 Seed opcional: popula CatRoupa com as coleções já usadas no front-end.
 Remova se preferir iniciar o catálogo vazio.
 */
INSERT INTO CatRoupa (CatRId, CatRSexo, CatRTipo, CatRSessao)
VALUES (1, 'Unissex', 'Calçados', 'Sneakers & Tênis'),
    (2, 'Unissex', 'Calças', 'Bottoms'),
    (3, 'Unissex', 'Blusas', 'Casacos & Hoodies'),
    (4, 'Unissex', 'Camisas', 'T-shirts & Shirts'),
    (
        5,
        'Unissex',
        'Conjuntos',
        'Sets & Matching Fits'
    ),
    (6, 'Unissex', 'Acessórios', 'Bonés & Outros');
/*
 Seed opcional: replica o portfólio completo de produtos utilizado no ambiente atual,
 incluindo caminhos dos modelos 3D e imagens 2D.
 */
INSERT INTO Roupa (
        RoupaId,
        RoupaNome,
        RoupaModelUrl,
        RoupaImgUrl,
        RoupaValor,
        CatRId
    )
VALUES (
        1,
        'Tênis Nike Air Jordan',
        '/models/calcados/nike_air_jordan/scene.gltf',
        'img/tenisjordan.png',
        449.90,
        1
    ),
    (
        2,
        'Nike Phantom',
        '/models/calcados/nike_fotball_shoe/scene.gltf',
        'img/phantom.png',
        329.90,
        1
    ),
    (
        3,
        'Adidas Ozelia',
        '/models/calcados/adidas_ozelia/scene.gltf',
        'img/ozelia.png',
        299.90,
        1
    ),
    (
        4,
        'Nike SB',
        '/models/calcados/nike_sb_charge_cnvs/scene.gltf',
        'img/nikesb.png',
        359.90,
        1
    ),
    (
        5,
        'Yeezy',
        '/models/calcados/glow_green_yeezy_slides/scene.gltf',
        'img/yeezy.png',
        489.90,
        1
    ),
    (
        6,
        'AIR JORDAN 4 LIGHTNING GS',
        '/models/calcados/air_jordan_4_lightning_gs/scene.gltf',
        'img/air_jordan_4_lightning_gs.png',
        539.90,
        1
    ),
    (
        7,
        'AIR JORDAN 7 PATTA',
        '/models/calcados/air_jordan_7_patta/scene.gltf',
        'img/air_jordan_7_patta.png',
        429.90,
        1
    ),
    (
        8,
        'Balenciaga Track Black',
        '/models/calcados/balenciaga_track_black/scene.gltf',
        'img/balenciaga_track_black.png',
        599.90,
        1
    ),
    (
        9,
        'Balenciaga Track White',
        '/models/calcados/balenciaga_track_white/scene.gltf',
        'img/balenciaga_track_white.png',
        589.90,
        1
    ),
    (
        10,
        'Balenciaga Triple S',
        '/models/calcados/balenciaga_triple_s_beige_green_yellow_2018/scene.gltf',
        'img/balenciaga_triple_s_beige_green_yellow_2018.png',
        629.90,
        1
    ),
    (
        11,
        'NIKE AIR FORCE 1 LOW TRAVIS SCOTT S',
        '/models/calcados/nike_air_force_1_low_travis_scott_cactus_jack/scene.gltf',
        'img/nike_air_force_1_low_travis_scott_cactus_jack.png',
        459.90,
        1
    ),
    (
        12,
        'NIKE AIR FORCE 1 LOW WHITE',
        '/models/calcados/nike_air_force_1_low_white/scene.gltf',
        'img/nike_air_force_1_low_white.png',
        299.90,
        1
    ),
    (
        13,
        'NIKE AIR FORCE 1 MID WHITE',
        '/models/calcados/nike_air_force_1_mid_white/scene.gltf',
        'img/nike_air_force_1_mid_white.png',
        329.90,
        1
    ),
    (
        14,
        'NIKE DUNK LOW OFF WHITE LOT 1',
        '/models/calcados/nike_dunk_low_off_white_lot_1/scene.gltf',
        'img/nike_dunk_low_off_white_lot_1.png',
        479.90,
        1
    ),
    (
        15,
        'NIKE DUNK OFF WHITE LOT 33',
        '/models/calcados/nike_dunk_off_white_lot_33/scene.gltf',
        'img/nike_dunk_off_white_lot_33.png',
        449.90,
        1
    ),
    (
        16,
        'Tiempo Legend',
        '/models/calcados/2014_-_tiempo_legend_-_8238169/scene.gltf',
        'img/tiempo.png',
        349.90,
        1
    ),
    (
        17,
        'Nike Dunk',
        '/models/calcados/nike_dunk/scene.gltf',
        'img/dunk.png',
        389.90,
        1
    ),
    (
        18,
        'Jordan 4 Retro SB Pine Green',
        '/models/calcados/jordan_4_retro_sb_pine_green/scene.gltf',
        'img/jordan4.png',
        559.90,
        1
    ),
    (
        19,
        'Nike air 720',
        '/models/calcados/nike_air_720/scene.gltf',
        'img/air720.png',
        319.90,
        1
    ),
    (
        20,
        'Baggy Pants',
        '/models/streetwears/baggy_pants_free/scene.gltf',
        'img/baggy_pants.png',
        139.90,
        2
    ),
    (
        21,
        'Black Pants',
        '/models/streetwears/black_white_pants_model/scene.gltf',
        'img/blackpants.png',
        149.90,
        2
    ),
    (
        22,
        'Oversize Baggy',
        '/models/streetwears/oversized_baggy_custom_jeans/scene.gltf',
        'img/oversizebaggy.png',
        129.90,
        2
    ),
    (
        23,
        'Rave Pants',
        '/models/streetwears/rave_pants__phat_pants/scene.gltf',
        'img/ravepants.png',
        139.90,
        2
    ),
    (
        24,
        'Skinny Pants',
        '/models/streetwears/skinny_pants_free/scene.gltf',
        'img/skinnypants.png',
        149.90,
        2
    ),
    (
        25,
        'Jeans Feminino',
        '/models/streetwears/jeansfem/scene.gltf',
        'img/jeansfem.png',
        99.90,
        2
    ),
    (
        26,
        'Jeans Masculino',
        '/models/streetwears/jeansmasc/scene.gltf',
        'img/jeansmasc.png',
        109.90,
        2
    ),
    (
        27,
        'Jnco Twin Baggy',
        '/models/streetwears/jnco_twin_cannon_baggy_jeans/scene.gltf',
        'img/jncotwin_baggy.png',
        159.90,
        2
    ),
    (
        28,
        'Sweet Pants',
        '/models/streetwears/my_sweet_piano_pants_with_bones/scene.gltf',
        'img/sweetpants.png',
        129.90,
        2
    ),
    (
        29,
        'Corrupted Pants',
        '/models/streetwears/corrupted_hoodie_and_pants/scene.gltf',
        'img/corruptedpants.png',
        119.90,
        5
    ),
    (
        30,
        'Roadies Pants',
        '/models/streetwears/roadies_hoodie_and_pants/scene.gltf',
        'img/roadiespants.png',
        119.90,
        5
    ),
    (
        31,
        'Conjunto Amarelo',
        '/models/conjunto_3/scene.gltf',
        'img/conjuntoamarelo.png',
        189.90,
        5
    ),
    (
        32,
        'Conjunto Azul',
        '/models/conjunto_2/scene.gltf',
        'img/conjuntoazul.png',
        189.90,
        5
    ),
    (
        33,
        'Conjunto Branco',
        '/models/conjunto_6/scene.gltf',
        'img/conjuntobranco.png',
        179.90,
        5
    ),
    (
        34,
        'Conjunto Dark',
        '/models/conjunto_1/scene.gltf',
        'img/conjuntodark.png',
        199.90,
        5
    ),
    (
        35,
        'Conjunto Preto',
        '/models/conjunto_5/scene.gltf',
        'img/conjuntopreto.png',
        189.90,
        5
    ),
    (
        36,
        'Conjunto Verde',
        '/models/conjunto_8/scene.gltf',
        'img/conjuntoverde.png',
        199.90,
        5
    ),
    (
        37,
        'Conjunto Vermelho',
        '/models/conjunto_4/scene.gltf',
        'img/conjuntovermelho.png',
        199.90,
        5
    ),
    (
        38,
        'Conjunto White',
        '/models/conjunto_7/scene.gltf',
        'img/conjuntowhite.png',
        189.90,
        5
    ),
    (
        39,
        'Iridescent Coat',
        '/models/streetwears/iridescent_coat/scene.gltf',
        'img/iridescent_coat.png',
        199.90,
        3
    ),
    (
        40,
        'Leather Jacket',
        '/models/streetwears/leather_jacket/scene.gltf',
        'img/leather_jacket.png',
        229.90,
        3
    ),
    (
        41,
        'Oversize Sweater',
        '/models/streetwears/oversized_sweater/scene.gltf',
        'img/oversizesweater.png',
        159.90,
        3
    ),
    (
        42,
        'Sweet Hoodie',
        '/models/streetwears/my_sweet_piano_hoodie_with_bones/scene.gltf',
        'img/sweethoodie.png',
        189.90,
        3
    ),
    (
        43,
        'Classic Black Flame Hoodie',
        '/models/streetwears/classic_black_flame_hoodie/scene.gltf',
        'img/classic_black_flame_hoodie.png',
        189.90,
        3
    ),
    (
        44,
        'Green Shirt Hood Scan Medpoly',
        '/models/streetwears/green_shirt_hood_scan_medpoly/scene.gltf',
        'img/green_shirt_hood_scan_medpoly.png',
        159.90,
        3
    ),
    (
        45,
        'Oversized Hoodie',
        '/models/streetwears/oversized_hoodie/scene.gltf',
        'img/oversized_hoodie.png',
        189.90,
        3
    ),
    (
        46,
        'Tshirt',
        '/models/streetwears/oversized_t-shirt/scene.gltf',
        'img/tshirt.png',
        79.90,
        4
    ),
    (
        47,
        'T-shirt Amazigh Traditional',
        '/models/streetwears/amazigh_traditional_t-shirt/scene.gltf',
        'img/amazigh_traditional_t-shirt.png',
        89.90,
        4
    ),
    (
        48,
        'T-shirt Red',
        '/models/streetwears/red_t-shirt/scene.gltf',
        'img/red_t-shirt.png',
        79.90,
        4
    ),
    (
        49,
        'T-shirt Amazigh',
        '/models/streetwears/amazigh_t-shirt/scene.gltf',
        'img/amazigh_t-shirt.png',
        89.90,
        4
    ),
    (
        50,
        'Shirt Black',
        '/models/streetwears/shirt/scene.gltf',
        'img/shirt.png',
        109.90,
        4
    ),
    (
        51,
        'Shirt The Punisher',
        '/models/streetwears/shirt_the_punisher/scene.gltf',
        'img/shirt_the_punisher.png',
        99.90,
        4
    ),
    (
        52,
        'Cool Shirt Scan Medpoly',
        '/models/streetwears/cool_shirt_scan_medpoly/scene.gltf',
        'img/cool_shirt_scan_medpoly.png',
        129.90,
        4
    ),
    (
        53,
        'Teemu Selannes Hockey Shirt',
        '/models/streetwears/teemu_selannes_hockey_shirt/scene.gltf',
        'img/teemu_selannes_hockey_shirt.png',
        149.90,
        4
    ),
    (
        54,
        'FC Porto Shirt',
        '/models/streetwears/fc_porto_concept_shirt/scene.gltf',
        'img/fc_porto_concept_shirt.png',
        169.90,
        4
    ),
    (
        55,
        'White T-shirt With Print',
        '/models/streetwears/white_t-shirt_with_print/scene.gltf',
        'img/white_t-shirt_with_print.png',
        89.90,
        4
    ),
    (
        56,
        'Mens Caro Flannel Shirt',
        '/models/streetwears/mens_caro_flannel_shirt/scene.gltf',
        'img/mens_caro_flannel_shirt.png',
        139.90,
        4
    ),
    (
        57,
        'Paris Moon Upside Down T-shirt',
        '/models/streetwears/paris_moon_upside_down_t-shirt/scene.gltf',
        'img/paris_moon_upside_down_t-shirt.png',
        109.90,
        4
    ),
    (
        58,
        'Monalisa T-shirt',
        '/models/streetwears/off-white_monalisa_black_t-shirt/scene.gltf',
        'img/off-white_monalisa_black_t-shirt.png',
        129.90,
        4
    ),
    (
        59,
        'Cap',
        '/models/streetwears/cap/scene.gltf',
        'img/cap.png',
        49.90,
        6
    ),
    (
        60,
        'Baseball Cap NY',
        '/models/streetwears/baseball_cap_ny/scene.gltf',
        'img/baseball_cap_ny.png',
        59.90,
        6
    ),
    (
        61,
        'Samurai Cap',
        '/models/streetwears/samurai_cap_3d_model/scene.gltf',
        'img/samurai_cap_3d_model.png',
        69.90,
        6
    ),
    (
        62,
        'Red Ice Cap',
        '/models/streetwears/red_ice_cap/scene.gltf',
        'img/red_ice_cap.png',
        59.90,
        6
    ),
    (
        63,
        'Gucci Hat Model White',
        '/models/streetwears/gucci_hat_model_white/scene.gltf',
        'img/gucci_hat_model_white.png',
        79.90,
        6
    );
