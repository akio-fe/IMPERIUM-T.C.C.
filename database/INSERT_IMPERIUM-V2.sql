/*
 ============================================================
 ARQUIVO: INSERT_IMPERIUM-V2.sql
 PROPÓSITO: SEED DATA PARA DESENVOLVIMENTO E TESTES
 ============================================================
 
 Este arquivo contém dados iniciais para popular o banco IMPERIUM com:
 - 2 usuários de teste (clientes)
 - 12 categorias de produtos (6 masculinas + 6 femininas)
 - 63 produtos completos com modelos 3D
 
 IMPORTANTE:
 - Este arquivo é executado APÓS IMPERIUM_COMENTADO-V6.sql
 - Em produção, remover/modificar estes dados
 - UIDs do Firebase são REAIS e devem ser mantidos em sincronização
 - Imagens e modelos 3D devem existir nos caminhos especificados
 
 ESTRUTURA:
 1. Usuários de teste
 2. Categorias de roupas (CatRoupa)
 3. Produtos (Roupa) com modelos 3D
 
 ============================================================
*/

/*
 ============================================================
 SEÇÃO 1: USUÁRIOS DE TESTE
 ============================================================
 
 Propósito: 
 Criar contas de usuário para testes de autenticação, carrinho,
 favoritos e checkout durante o desenvolvimento.
 
 Características dos Usuários:
 - UsuUID: ID real do Firebase Authentication (sincronizado)
 - UsuFuncao: Todos definidos como 'CLIENTE' para testes de e-commerce
 - UsuEmail: E-mails reais dos desenvolvedores
 - UsuCpf/UsuTel: Dados fictícios para testes
 
 Integração Firebase:
 - UIDs devem corresponder aos usuários criados no console Firebase
 - Login.php valida token JWT e busca usuário por UsuUID
 - Alterações aqui devem ser refletidas no Firebase e vice-versa
 
 Segurança em Produção:
 - REMOVER estes registros antes do deploy
 - Usuários reais serão criados via registro no site
 - Não manter senhas ou dados sensíveis aqui
 */
DELETE FROM `usuario`;
INSERT INTO `usuario` (
        `UsuId`,        -- ID sequencial interno do banco
        `UsuUID`,       -- UID único do Firebase Authentication
        `UsuEmail`,     -- E-mail para login e comunicação
        `UsuNome`,      -- Nome completo do usuário
        `UsuCpf`,       -- CPF (apenas números) - dados fictícios
        `UsuTel`,       -- Telefone com DDD - dados fictícios
        `UsuDataNasc`,  -- Data de nascimento para validação de maioridade
        `UsuFuncao`     -- CLIENTE | FUNCIONARIO | GERENTE
    )
VALUES 
    -- Usuário 1: Desenvolvedor/Administrador do projeto
    (
        1,
        'lRE2GD8ZkBet0AIZyXw1sYNQeUH2',           -- UID Firebase real
        'fe.akio20@gmail.com',                     -- E-mail de teste
        'Fernando Akio Carreiro',                  -- Nome completo
        '45316952839',                             -- CPF fictício
        '11930234270',                             -- Telefone fictício
        '2008-05-20',                              -- Data de nascimento
        'CLIENTE'                                  -- Perfil padrão
    ),
    -- Usuário 2: Testador/Desenvolvedor adicional
    (
        2,
        '3N9EheeXVDPY2sMRtm6SkNFutS63',           -- UID Firebase real
        'natgretakaleo@gmail.com',                 -- E-mail de teste
        'Natália Medeiros Ando',                   -- Nome completo
        '00000000000',                             -- CPF fictício (placeholder)
        '11111111111',                             -- Telefone fictício (placeholder)
        '2009-03-30',                              -- Data de nascimento
        'CLIENTE'                                  -- Perfil padrão
    );
/*
 ============================================================
 SEÇÃO 2: CATEGORIAS DE PRODUTOS
 ============================================================
 
 Propósito: 
 Definir a taxonomia completa de produtos do e-commerce IMPERIUM,
 permitindo navegação organizada e filtragem eficiente.
 
 Estrutura de Categoria (CatRoupa):
 - CatRId: ID único da categoria (usado em Roupa.CatRId)
 - CatRSexo: Segmentação por público-alvo (Masculino/Feminino)
 - CatRTipo: Tipo principal da peça (Calçados, Calças, Blusas, etc)
 - CatRSessao: Nome comercial/marketing da categoria
 
 Arquitetura de Categorização:
 - Total: 12 categorias (6 masculinas + 6 femininas)
 - Simetria: cada tipo existe em ambos os gêneros
 - Permite expansão futura: Infantil, Unissex, Plus Size, etc
 
 Mapeamento Front-end:
 - index.php usa CatRId para filtrar produtos por categoria
 - URLs amigáveis: /shop?filtro=calcados, /shop?filtro=camisas
 - Breadcrumbs: Home > Masculino > Calçados > Produto
 
 Uso em Queries:
 SELECT * FROM roupa WHERE CatRId IN (1,7);  -- Todos os calçados
 SELECT * FROM roupa WHERE CatRId BETWEEN 1 AND 6;  -- Produtos masculinos
 SELECT * FROM roupa WHERE CatRId BETWEEN 7 AND 12; -- Produtos femininos
 
 Regras de Negócio:
 - CatRSexo: usado para filtros opcionais, não obrigatórios
 - Mesmo produto pode ter variações masculina/feminina
 - CatRTipo: agrupamento lógico para navegação do site
 - CatRSessao: texto exibido na interface (pode ter acentos/emojis)
 */
DELETE FROM `catroupa`;
INSERT INTO `catroupa` (
        `CatRId`,       -- ID sequencial único da categoria
        `CatRSexo`,     -- Masculino | Feminino | Unissex
        `CatRTipo`,     -- Tipo de peça (usado em filtros)
        `CatRSessao`    -- Nome comercial (exibido no site)
    )
VALUES 
    -- ==================== CATEGORIAS MASCULINAS (IDs 1-6) ====================
    
    -- Categoria 1: Calçados Masculinos
    -- Produtos: Tênis, sneakers, sapatos casuais, chuteiras
    (1, 'Masculino', 'Calçados', 'Sneakers & Tênis'),
    
    -- Categoria 2: Calças Masculinas
    -- Produtos: Jeans, calças cargo, joggers, shorts
    (2, 'Masculino', 'Calças', 'Bottoms'),
    
    -- Categoria 3: Blusas Masculinas
    -- Produtos: Hoodies, moletons, casacos, jaquetas
    (3, 'Masculino', 'Blusas', 'Casacos & Hoodies'),
    
    -- Categoria 4: Camisas Masculinas
    -- Produtos: T-shirts, camisetas, polos, jerseys
    (4, 'Masculino', 'Camisas', 'T-shirts & Shirts'),
    
    -- Categoria 5: Conjuntos Masculinos
    -- Produtos: Sets coordenados (agasalhos, treino, street)
    (5, 'Masculino', 'Conjuntos', 'Sets & Matching Fits'),
    
    -- Categoria 6: Acessórios Masculinos
    -- Produtos: Bonés, chapéus, meias, cintos, relógios
    (6, 'Masculino', 'Acessorios', 'Bonés & Outros'),
    
    -- ==================== CATEGORIAS FEMININAS (IDs 7-12) ====================
    
    -- Categoria 7: Calçados Femininos
    -- Produtos: Tênis, sneakers, sandálias, botas
    (7, 'Feminino', 'Calçados', 'Sneakers & Tênis'),
    
    -- Categoria 8: Calças Femininas
    -- Produtos: Jeans, leggings, shorts, saias-calça
    (8, 'Feminino', 'Calças', 'Bottoms'),
    
    -- Categoria 9: Blusas Femininas
    -- Produtos: Hoodies, cardigans, jaquetas, blazers
    (9, 'Feminino', 'Blusas', 'Casacos & Hoodies'),
    
    -- Categoria 10: Camisas Femininas
    -- Produtos: T-shirts, camisetas, blusas, tops
    (10, 'Feminino', 'Camisas', 'T-shirts & Shirts'),
    
    -- Categoria 11: Conjuntos Femininos
    -- Produtos: Sets coordenados (fitness, casual, festa)
    (11, 'Feminino', 'Conjuntos', 'Sets & Matching Fits'),
    
    -- Categoria 12: Acessórios Femininos
    -- Produtos: Bonés, chapéus, bolsas, bijuterias
    (12, 'Feminino', 'Acessorios', 'Bonés & Outros');
/*
 ============================================================
 SEÇÃO 3: CATÁLOGO COMPLETO DE PRODUTOS
 ============================================================
 
 Propósito: 
 Popular o banco com 63 produtos reais para desenvolvimento, testes e demonstração.
 Cada produto inclui modelo 3D interativo para visualização no navegador.
 
 Tecnologia de Visualização 3D:
 - Formato: GLTF (GL Transmission Format) - padrão da indústria para web 3D
 - Renderização: Three.js no navegador (WebGL)
 - Interatividade: Rotação 360°, zoom, pan, visualização de texturas
 - Performance: Modelos otimizados (<5MB) para carregamento rápido
 - Compatibilidade: Funciona em desktop, tablet e mobile
 
 Estrutura de Armazenamento:
 - Modelos 3D: /storage/models/{categoria}/{produto}/scene.gltf
 - Imagens 2D: /public/assets/img/catalog/{produto}.png
 - Thumbnails: Gerados automaticamente pelo navegador
 
 Estrutura de Produto (Roupa):
 - RoupaNome: Nome comercial exibido no site
 - RoupaModelUrl: Caminho para modelo 3D (GLTF/GLB) usado pelo visualizador Three.js
 - RoupaImgUrl: Caminho para imagem 2D (PNG) usada em cards e thumbnails
 - RoupaValor: Preço em reais (R$) com centavos
 - CatRId: Categoria à qual o produto pertence
 
 Distribuição por Categoria:
 - Calçados (CatRId=1): 19 produtos
 * Tênis Nike, Adidas, Jordan, Balenciaga, Yeezy
 * Foco em sneakers e streetwear
 
 - Calças (CatRId=2): 9 produtos
 * Baggy pants, jeans, skinny pants
 * Estilos masculinos e femininos
 
 - Blusas/Casacos (CatRId=3): 7 produtos
 * Hoodies, casacos, sweaters
 * Peças oversized e streetwear
 
 - Camisas (CatRId=4): 14 produtos
 * T-shirts, camisas estampadas, jerseys
 * Maior variedade de produtos
 
 - Conjuntos (CatRId=5): 8 produtos
 * Sets completos (top + bottom)
 * Coordenados por cor
 
 - Acessórios (CatRId=6): 5 produtos
 * Bonés, chapéus
 * Complementos de look
 
 Tecnologia de Visualização:
 - Modelos 3D em formato GLTF (GL Transmission Format)
 - Renderização via Three.js no navegador
 - Permite rotação 360°, zoom, e visualização interativa
 - Arquivos armazenados em /storage/models/
 
 Faixa de Preço:
 - Mínimo: R$ 49,90 (acessórios básicos)
 - Máximo: R$ 629,90 (tênis premium)
 - Média: R$ 200,00 aproximadamente
 
 Nota: Em produção, remover esta seção ou substituir por produtos reais.
 */
DELETE FROM `roupa`;
INSERT INTO `roupa` (
        `RoupaId`,
        `RoupaNome`,
        `RoupaModelUrl`,
        `RoupaImgUrl`,
        `RoupaValor`,
        `CatRId`
    )
VALUES -- CALÇADOS MASCULINOS (CatRId=1)
    (
        1,
        'Tênis Nike Air Jordan',
        '/storage/models/calcados/nike_air_jordan/scene.gltf',
        'public/assets/img/catalog/tenisjordan.png',
        449.90,
        1
    ),
    (
        2,
        'Nike Phantom',
        '/storage/models/calcados/nike_fotball_shoe/scene.gltf',
        'public/assets/img/catalog/phantom.png',
        329.90,
        1
    ),
    (
        3,
        'Nike SB',
        '/storage/models/calcados/nike_sb_charge_cnvs/scene.gltf',
        'public/assets/img/catalog/nikesb.png',
        359.90,
        1
    ),
    (
        4,
        'AIR JORDAN 4 LIGHTNING GS',
        '/storage/models/calcados/air_jordan_4_lightning_gs/scene.gltf',
        'public/assets/img/catalog/air_jordan_4_lightning_gs.png',
        539.90,
        1
    ),
    (
        5,
        'AIR JORDAN 7 PATTA',
        '/storage/models/calcados/air_jordan_7_patta/scene.gltf',
        'public/assets/img/catalog/air_jordan_7_patta.png',
        429.90,
        1
    ),
    (
        6,
        'NIKE AIR FORCE 1 LOW TRAVIS SCOTT S',
        '/storage/models/calcados/nike_air_force_1_low_travis_scott_cactus_jack/scene.gltf',
        'public/assets/img/catalog/nike_air_force_1_low_travis_scott_cactus_jack.png',
        459.90,
        1
    ),
    (
        7,
        'NIKE AIR FORCE 1 LOW WHITE',
        '/storage/models/calcados/nike_air_force_1_low_white/scene.gltf',
        'public/assets/img/catalog/nike_air_force_1_low_white.png',
        299.90,
        1
    ),
    (
        8,
        'NIKE AIR FORCE 1 MID WHITE',
        '/storage/models/calcados/nike_air_force_1_mid_white/scene.gltf',
        'public/assets/img/catalog/nike_air_force_1_mid_white.png',
        329.90,
        1
    ),
    (
        9,
        'NIKE DUNK LOW OFF WHITE LOT 1',
        '/storage/models/calcados/nike_dunk_low_off_white_lot_1/scene.gltf',
        'public/assets/img/catalog/nike_dunk_low_off_white_lot_1.png',
        479.90,
        1
    ),
    (
        10,
        'NIKE DUNK OFF WHITE LOT 33',
        '/storage/models/calcados/nike_dunk_off_white_lot_33/scene.gltf',
        'public/assets/img/catalog/nike_dunk_off_white_lot_33.png',
        449.90,
        1
    ),
    (
        11,
        'Tiempo Legend',
        '/storage/models/calcados/2014_-_tiempo_legend_-_8238169/scene.gltf',
        'public/assets/img/catalog/tiempo.png',
        349.90,
        1
    ),
    (
        12,
        'Nike Dunk',
        '/storage/models/calcados/nike_dunk/scene.gltf',
        'public/assets/img/catalog/dunk.png',
        389.90,
        1
    ),
    (
        13,
        'Jordan 4 Retro SB Pine Green',
        '/storage/models/calcados/jordan_4_retro_sb_pine_green/scene.gltf',
        'public/assets/img/catalog/jordan4.png',
        559.90,
        1
    ),
    (
        14,
        'Nike air 720',
        '/storage/models/calcados/nike_air_720/scene.gltf',
        'public/assets/img/catalog/air720.png',
        319.90,
        1
    ),
    -- CALÇADOS FEMININOS (CatRId=7)
    (
        15,
        'Adidas Ozelia',
        '/storage/models/calcados/adidas_ozelia/scene.gltf',
        'public/assets/img/catalog/ozelia.png',
        299.90,
        7
    ),
    (
        16,
        'Yeezy',
        '/storage/models/calcados/glow_green_yeezy_slides/scene.gltf',
        'public/assets/img/catalog/yeezy.png',
        489.90,
        7
    ),
    (
        17,
        'Balenciaga Track Black',
        '/storage/models/calcados/balenciaga_track_black/scene.gltf',
        'public/assets/img/catalog/balenciaga_track_black.png',
        599.90,
        7
    ),
    (
        18,
        'Balenciaga Track White',
        '/storage/models/calcados/balenciaga_track_white/scene.gltf',
        'public/assets/img/catalog/balenciaga_track_white.png',
        589.90,
        7
    ),
    (
        19,
        'Balenciaga Triple S',
        '/storage/models/calcados/balenciaga_triple_s_beige_green_yellow_2018/scene.gltf',
        'public/assets/img/catalog/balenciaga_triple_s_beige_green_yellow_2018.png',
        629.90,
        7
    ),
    -- CALÇAS MASCULINAS (CatRId=2)
    (
        20,
        'Baggy Pants',
        '/storage/models/streetwears/baggy_pants_free/scene.gltf',
        'public/assets/img/catalog/baggy_pants.png',
        139.90,
        2
    ),
    (
        21,
        'Black Pants',
        '/storage/models/streetwears/black_white_pants_model/scene.gltf',
        'public/assets/img/catalog/blackpants.png',
        149.90,
        2
    ),
    (
        22,
        'Oversize Baggy',
        '/storage/models/streetwears/oversized_baggy_custom_jeans/scene.gltf',
        'public/assets/img/catalog/oversizebaggy.png',
        129.90,
        2
    ),
    (
        23,
        'Rave Pants',
        '/storage/models/streetwears/rave_pants__phat_pants/scene.gltf',
        'public/assets/img/catalog/ravepants.png',
        139.90,
        2
    ),
    (
        24,
        'Jeans Masculino',
        '/storage/models/streetwears/jeansmasc/scene.gltf',
        'public/assets/img/catalog/jeansmasc.png',
        109.90,
        2
    ),
    (
        25,
        'Jnco Twin Baggy',
        '/storage/models/streetwears/jnco_twin_cannon_baggy_jeans/scene.gltf',
        'public/assets/img/catalog/jncotwin_baggy.png',
        159.90,
        2
    ),
    -- CALÇAS FEMININAS (CatRId=8)
    (
        26,
        'Skinny Pants',
        '/storage/models/streetwears/skinny_pants_free/scene.gltf',
        'public/assets/img/catalog/skinnypants.png',
        149.90,
        8
    ),
    (
        27,
        'Jeans Feminino',
        '/storage/models/streetwears/jeansfem/scene.gltf',
        'public/assets/img/catalog/jeansfem.png',
        99.90,
        8
    ),
    (
        28,
        'Sweet Pants',
        '/storage/models/streetwears/my_sweet_piano_pants_with_bones/scene.gltf',
        'public/assets/img/catalog/sweetpants.png',
        129.90,
        8
    ),
    -- CONJUNTOS MASCULINOS (CatRId=5)
    (
        29,
        'Corrupted Pants',
        '/storage/models/streetwears/corrupted_hoodie_and_pants/scene.gltf',
        'public/assets/img/catalog/corruptedpants.png',
        119.90,
        5
    ),
    (
        30,
        'Roadies Pants',
        '/storage/models/streetwears/roadies_hoodie_and_pants/scene.gltf',
        'public/assets/img/catalog/roadiespants.png',
        119.90,
        5
    ),
    (
        31,
        'Conjunto Dark',
        '/storage/models/conjunto_1/scene.gltf',
        'public/assets/img/catalog/conjuntodark.png',
        199.90,
        5
    ),
    (
        32,
        'Conjunto Azul',
        '/storage/models/conjunto_2/scene.gltf',
        'public/assets/img/catalog/conjuntoazul.png',
        189.90,
        5
    ),
    (
        33,
        'Conjunto Preto',
        '/storage/models/conjunto_5/scene.gltf',
        'public/assets/img/catalog/conjuntopreto.png',
        189.90,
        5
    ),
    -- CONJUNTOS FEMININOS (CatRId=11)
    (
        34,
        'Conjunto Amarelo',
        '/storage/models/conjunto_3/scene.gltf',
        'public/assets/img/catalog/conjuntoamarelo.png',
        189.90,
        11
    ),
    (
        35,
        'Conjunto Branco',
        '/storage/models/conjunto_6/scene.gltf',
        'public/assets/img/catalog/conjuntobranco.png',
        179.90,
        11
    ),
    (
        36,
        'Conjunto Verde',
        '/storage/models/conjunto_8/scene.gltf',
        'public/assets/img/catalog/conjuntoverde.png',
        199.90,
        11
    ),
    (
        37,
        'Conjunto Vermelho',
        '/storage/models/conjunto_4/scene.gltf',
        'public/assets/img/catalog/conjuntovermelho.png',
        199.90,
        11
    ),
    (
        38,
        'Conjunto White',
        '/storage/models/conjunto_7/scene.gltf',
        'public/assets/img/catalog/conjuntowhite.png',
        189.90,
        11
    ),
    -- BLUSAS MASCULINAS (CatRId=3)
    (
        39,
        'Leather Jacket',
        '/storage/models/streetwears/leather_jacket/scene.gltf',
        'public/assets/img/catalog/leather_jacket.png',
        229.90,
        3
    ),
    (
        40,
        'Classic Black Flame Hoodie',
        '/storage/models/streetwears/classic_black_flame_hoodie/scene.gltf',
        'public/assets/img/catalog/classic_black_flame_hoodie.png',
        189.90,
        3
    ),
    (
        41,
        'Green Shirt Hood Scan Medpoly',
        '/storage/models/streetwears/green_shirt_hood_scan_medpoly/scene.gltf',
        'public/assets/img/catalog/green_shirt_hood_scan_medpoly.png',
        159.90,
        3
    ),
    (
        42,
        'Oversized Hoodie',
        '/storage/models/streetwears/oversized_hoodie/scene.gltf',
        'public/assets/img/catalog/oversized_hoodie.png',
        189.90,
        3
    ),
    -- BLUSAS FEMININAS (CatRId=9)
    (
        43,
        'Iridescent Coat',
        '/storage/models/streetwears/iridescent_coat/scene.gltf',
        'public/assets/img/catalog/iridescent_coat.png',
        199.90,
        9
    ),
    (
        44,
        'Oversize Sweater',
        '/storage/models/streetwears/oversized_sweater/scene.gltf',
        'public/assets/img/catalog/oversizesweater.png',
        159.90,
        9
    ),
    (
        45,
        'Sweet Hoodie',
        '/storage/models/streetwears/my_sweet_piano_hoodie_with_bones/scene.gltf',
        'public/assets/img/catalog/sweethoodie.png',
        189.90,
        9
    ),
    -- CAMISAS MASCULINAS (CatRId=4)
    (
        46,
        'Tshirt',
        '/storage/models/streetwears/oversized_t-shirt/scene.gltf',
        'public/assets/img/catalog/tshirt.png',
        79.90,
        4
    ),
    (
        47,
        'T-shirt Amazigh Traditional',
        '/storage/models/streetwears/amazigh_traditional_t-shirt/scene.gltf',
        'public/assets/img/catalog/amazigh_traditional_t-shirt.png',
        89.90,
        4
    ),
    (
        48,
        'T-shirt Amazigh',
        '/storage/models/streetwears/amazigh_t-shirt/scene.gltf',
        'public/assets/img/catalog/amazigh_t-shirt.png',
        89.90,
        4
    ),
    (
        49,
        'Shirt Black',
        '/storage/models/streetwears/shirt/scene.gltf',
        'public/assets/img/catalog/shirt.png',
        109.90,
        4
    ),
    (
        50,
        'Shirt The Punisher',
        '/storage/models/streetwears/shirt_the_punisher/scene.gltf',
        'public/assets/img/catalog/shirt_the_punisher.png',
        99.90,
        4
    ),
    (
        51,
        'Cool Shirt Scan Medpoly',
        '/storage/models/streetwears/cool_shirt_scan_medpoly/scene.gltf',
        'public/assets/img/catalog/cool_shirt_scan_medpoly.png',
        129.90,
        4
    ),
    (
        52,
        'Teemu Selannes Hockey Shirt',
        '/storage/models/streetwears/teemu_selannes_hockey_shirt/scene.gltf',
        'public/assets/img/catalog/teemu_selannes_hockey_shirt.png',
        149.90,
        4
    ),
    (
        53,
        'FC Porto Shirt',
        '/storage/models/streetwears/fc_porto_concept_shirt/scene.gltf',
        'public/assets/img/catalog/fc_porto_concept_shirt.png',
        169.90,
        4
    ),
    (
        54,
        'Mens Caro Flannel Shirt',
        '/storage/models/streetwears/mens_caro_flannel_shirt/scene.gltf',
        'public/assets/img/catalog/mens_caro_flannel_shirt.png',
        139.90,
        4
    ),
    (
        55,
        'Monalisa T-shirt',
        '/storage/models/streetwears/off-white_monalisa_black_t-shirt/scene.gltf',
        'public/assets/img/catalog/off-white_monalisa_black_t-shirt.png',
        129.90,
        4
    ),
    -- CAMISAS FEMININAS (CatRId=10)
    (
        56,
        'T-shirt Red',
        '/storage/models/streetwears/red_t-shirt/scene.gltf',
        'public/assets/img/catalog/red_t-shirt.png',
        79.90,
        10
    ),
    (
        57,
        'White T-shirt With Print',
        '/storage/models/streetwears/white_t-shirt_with_print/scene.gltf',
        'public/assets/img/catalog/white_t-shirt_with_print.png',
        89.90,
        10
    ),
    (
        58,
        'Paris Moon Upside Down T-shirt',
        '/storage/models/streetwears/paris_moon_upside_down_t-shirt/scene.gltf',
        'public/assets/img/catalog/paris_moon_upside_down_t-shirt.png',
        109.90,
        10
    ),
    -- ACESSÓRIOS MASCULINOS (CatRId=6)
    (
        59,
        'Cap',
        '/storage/models/streetwears/cap/scene.gltf',
        'public/assets/img/catalog/cap.png',
        49.90,
        6
    ),
    (
        60,
        'Baseball Cap NY',
        '/storage/models/streetwears/baseball_cap_ny/scene.gltf',
        'public/assets/img/catalog/baseball_cap_ny.png',
        59.90,
        6
    ),
    (
        61,
        'Samurai Cap',
        '/storage/models/streetwears/samurai_cap_3d_model/scene.gltf',
        'public/assets/img/catalog/samurai_cap_3d_model.png',
        69.90,
        6
    ),
    -- ACESSÓRIOS FEMININOS (CatRId=12)
    (
        62,
        'Red Ice Cap',
        '/storage/models/streetwears/red_ice_cap/scene.gltf',
        'public/assets/img/catalog/red_ice_cap.png',
        59.90,
        12
    ),
    (
        63,
        'Gucci Hat Model White',
        '/storage/models/streetwears/gucci_hat_model_white/scene.gltf',
        'public/assets/img/catalog/gucci_hat_model_white.png',
        79.90,
        12
    );