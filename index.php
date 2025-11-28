<?php
// index.php
session_start();
require_once __DIR__ . '/bootstrap/app.php';

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = '';
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

if (!in_array($filtro, $filtrosPermitidos, true)) {
    $filtro = 'todos';
}
$categoriaSlugMap = [
    1 => 'calcados',
    2 => 'calcas',
    3 => 'blusas',
    4 => 'camisas',
    5 => 'conjuntos',
    6 => 'acessorios',
];

$navItems = [
    'todos' => 'Todos',
    'camisas' => 'Camisas',
    'calcas' => 'Calças',
    'calcados' => 'Calçados',
    'acessorios' => 'Acessórios',
];

$navLinksHtml = "<a href='" . url_path('index.php') . "' class='active'>Home</a>";
foreach ($navItems as $slug => $label) {
    $shopUrl = url_path('public/pages/shop/index.php') . "?filtro={$slug}";
    $navLinksHtml .= "<a href='{$shopUrl}' data-tipo='{$slug}'>{$label}</a>";
}

$produtos = [];
$erroProdutos = '';

$sql = "SELECT r.RoupaId, r.RoupaNome, r.RoupaModelUrl, r.RoupaImgUrl, r.RoupaValor, r.CatRId, c.CatRTipo, c.CatRSessao\n        FROM roupa r\n        INNER JOIN catroupa c ON c.CatRId = r.CatRId\n        ORDER BY r.CatRId, r.RoupaNome";

if ($resultado = $conn->query($sql)) {
    while ($linha = $resultado->fetch_assoc()) {
        $linha['slug'] = $categoriaSlugMap[$linha['CatRId']] ?? 'outros';
        $produtos[] = $linha;
    }
    $resultado->free();
} else {
    $erroProdutos = 'Não foi possível carregar os produtos. Tente novamente em instantes.';
}

$logoSrc = asset_path('img/catalog/aguia.png');
$cartIcon = asset_path('img/catalog/carrin.png');
$profileIcon = asset_path('img/catalog/perfilzin.png');
$loginUrl = url_path('public/pages/auth/cadastro_login.html');
$cartLink = url_path('public/pages/shop/carrinho.php');
$profileLink = url_path('public/pages/account/perfil.php');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $header = "<header>
        <div class='linkLogin'>
            <a href='{$loginUrl}'><i class='fa-solid fa-user'></i>FAÇA LOGIN / CADASTRE-SE</a>
        </div>
        <nav>
            {$navLinksHtml}
        </nav>
        <img src='{$logoSrc}' alt='Imperium'>
    </header>";
} else {
    $header = "<header>
        <div class='acicons'>
                <a href='{$cartLink}''><img src='{$cartIcon}' alt='Carrinho'></a>
                <a href='{$profileLink}'><img src='{$profileIcon}' alt='Perfil'></a>
            </div> 
        
        <nav>{$navLinksHtml}</nav>

        <img src='{$logoSrc}' alt='Imperium'>
                   
    </header>";
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMPERIUM</title>
    <link rel="icon" href="<?= asset_path('img/catalog/icone.ico'); ?>">

    <!-- Inserção de ícones -->
    <script src="https://kit.fontawesome.com/bf477ee59c.js" crossorigin="anonymous"></script>


    <!-- Links CSS -->
    <link rel="stylesheet" href="<?= asset_path('css/header.css'); ?>">
    <link rel="stylesheet" href="<?= asset_path('css/style.css'); ?>">
    <link rel="stylesheet" href="<?= asset_path('css/body.css'); ?>">

</head>

<body>
    <!-- Cabeçalho da página-->
    <?php echo $header; ?>

    <!-- Roupas -->

    <main>

        <!-- Banner com slider -->

        <div class="slider">

            <div class="list">

                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/thething.png'); ?>" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">FORÇA</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/japanesestreet.png'); ?>" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">DOMINIO</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/balaclava.png'); ?>" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">PODER</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/light.png'); ?>" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">ESTILO</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

            </div>


            <div class="thumbnail">

                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/thething.png'); ?>" alt="">
                </div>
                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/japanesestreet.png'); ?>" alt="">
                </div>
                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/balaclava.png'); ?>" alt="">
                </div>
                <div class="item">
                    <img src="<?= asset_path('public/assets/img/ui/light.png'); ?>" alt="">
                </div>

            </div>


            <div class="nextPrevArrows">
                <button class="prev">
                    < </button>
                        <button class="next"> > </button>
            </div>


        </div>

        <!-- Seção de promoções-->
        <section>
            <div class="sectionEsq">
                <h2 style="margin-left: 85px;" id="prom">PROMOÇÕES</h2>
                <i class=" fa-solid fa-money-bills"></i>
            </div>
            <div class="secImgEsq">
                <img src="<?= asset_path('public/assets/img/ui/promos.jpg'); ?>" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção de mais vendidos -->
        <section>
            <div class="sectionDir">
                <h2>MAIS VENDIDOS</h2>
                <i class="fa-solid fa-fire"></i>
            </div>
            <div class="secImgDir">
                <img src="<?= asset_path('public/assets/img/ui/travis.jpg'); ?>" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção de recomendados -->
        <section>
            <div class="sectionEsq">
                <h2>RECOMENDADOS</h2>
                <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            <div class="secImgEsq">
                <img src="<?= asset_path('public/assets/img/ui/mahdi.jpg'); ?>" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção roupas masculinas -->
        <section>
            <div class="sectionDir">
                <h2 id="masc">MASCULINO</h2>
                <i class="fa-solid fa-shirt"></i>
            </div>
            <div class="secImgDir">
                <img src="<?= asset_path('public/assets/img/ui/modaMasc.png'); ?>" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção roupas femininas -->
        <section>
            <div class="sectionEsq">
                <h2 id="fem">FEMININO</h2>
                <i class="fa-solid fa-shirt"></i>   
            </div>
            <div class="secImgEsq">
                <img src="<?= asset_path('public/assets/img/ui/modaFem.png'); ?>" alt="imagem promocional">
            </div>
        </section>
    </main>


    <footer>
        <!-- footer da página-->

        <div class="container">

            <div class="conteudo_footer">
                <h2 id="inscricao">INSCREVA-SE PARA NOVIDADES E PROMOÇÕES EXCLUSIVAS</h2>

                <nav class="input-container">
                    <form action="cadastrar email de envio">
                        <input id="input" class="input" name="email" type="email" placeholder="Digite seu email"
                            title="Digite seu email" aria-label="Digite seu email">
                        <label class="label" for="input">Digite seu email</label>
                        <div class="topline"></div>
                        <div class="underline"></div>
                    </form>
                </nav>
            </div>

            <!-- links internos -->
            <div class="conteudo_footer" id="nossos_produtos">
                <h2>Nossos Produtos</h2>
                <nav>
                    <ul class="listas" type="none">
                        <li><a href="#" target="_self">Camisas oversized</a></li>
                        <li><a href="#" target="_self">Bonés</a></li>
                        <li><a href="#" target="_self">Calças cargo</a></li>
                        <li><a href="#" target="_self">Calças Jogger</a></li>
                        <li><a href="#" target="_self">Shorts Cargo</a></li>
                        <li><a href="#" target="_self">Outlet Sapatos</a></li>
                    </ul>
                </nav>
            </div>

            <!-- dúvidas -->
            <div class="conteudo_footer" id="duvidas">
                <h2>Dúvidas?</h2>
                <nav>
                    <ul class="listas" type="none">
                        <li><a href="#" target="_self">Rastrear Pedido</a></li>
                        <li><a href="#" target="self">Frete e Entrega</a></li>
                        <li><a href="#" target="blank">Trocas e Devoluções</a></li>
                        <li><a href="#" target="blank">Política de Privacidade</a></li>
                    </ul>
                </nav>
            </div>

            <!-- sobre nós -->
            <div class="Localizacao">
                <h2>Localização</h2>
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3657.673574582738!2d-46.641670924842956!3d-23.544240178811606!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce58519cff9bc3%3A0x6aa55e7150be1971!2sGaleria%20do%20Rock!5e0!3m2!1spt-BR!2sbr!4v1755172649365!5m2!1spt-BR!2sbr"
                    width="250" height="250" style="border: 1px;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </footer>

</body>

<script src="<?= asset_path('js/app.js'); ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.querySelector('.search-bar input');
        const fechar = document.querySelector('.search-bar .fechar');
        const lupa = document.querySelector('.icons .pesquisar');
        const icon = document.querySelector('.search-bar .search-icon');

        if (!input || !fechar || !lupa || !icon) {
            return;
        }

        lupa.addEventListener('click', () => {
            input.classList.add('mostrar');
            fechar.style.display = 'inline-block';
            lupa.style.display = 'none';
            icon.style.display = 'block';
        });

        fechar.addEventListener('click', () => {
            input.classList.remove('mostrar');
            fechar.style.display = 'none';
            lupa.style.display = 'inline-block';
            icon.style.display = 'none';
        });
    });
</script>

</html>