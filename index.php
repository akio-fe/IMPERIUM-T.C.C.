<?php
// index.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // O usuário não está logado, redireciona para a página de login
    $header = '<header>
        <img src="public/images/aguia.png" alt="">

        <nav>
            <a href="#" class="active">Home</a>
            <a href="php/PRODUTOS.php?filtro=todos">Todos</a>
            <a href="php/PRODUTOS.php?filtro=camisas">Camisas</a>
            <a href="php/PRODUTOS.php?filtro=calcas">Calças</a>
            <a href="php/PRODUTOS.php?filtro=calcados">Calçados</a>
            <a href="php/PRODUTOS.php?filtro=acessorios">Acessorios</a>
            <a href="php/PRODUTOS.php?filtro=contato">Contato</a>
        </nav>

        <div class="linkLogin">
            <a href="html/cadastro_login.html"><i class="fa-solid fa-user"></i>FAÇA LOGIN / CADASTRE-SE</a>
        </div>
    </header>';
} else {
    // Se o usuário está logado, exibe o cabeçalho para usuários logados
    $header = '<header>
        <img src="public/images/aguia.png" alt="">

        <nav>
            <a href="#" class="active">Home</a>
            <a href="php/PRODUTOS.php?filtro=todos">Todos</a>
            <a href="php/PRODUTOS.php?filtro=camisas">Camisas</a>
            <a href="php/PRODUTOS.php?filtro=calcas">Calças</a>
            <a href="php/PRODUTOS.php?filtro=calcados">Calçados</a>
            <a href="php/PRODUTOS.php?filtro=acessorios">Acessorios</a>
            <a href="php/PRODUTOS.php?filtro=contato">Contato</a>
        </nav>

        <div class="icons">
            <a href="php/perfil.php"><img src="img/perfilzin.png" alt="Perfil"></a>
        </div>
    </header>';
}


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMPERIUM</title>

    <!-- Inserção de ícones -->
    <script src="https://kit.fontawesome.com/bf477ee59c.js" crossorigin="anonymous"></script>


    <!-- Links CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/header.css">

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
                    <img src="public/images/thething.png" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">FORÇA</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="public/images/japanesestreet.png" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">DOMINIO</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="public/images/balaclava.png" alt="">

                    <div class="content">
                        <div class="title">USE IMPERIUM</div>
                        <div class="type">PODER</div>
                        <div class="button">
                            <button>VER MAIS</button>
                        </div>
                    </div>
                </div>

                <div class="item">
                    <img src="public/images/light.png" alt="">

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
                    <img src="public/images/thething.png" alt="">
                </div>
                <div class="item">
                    <img src="public/images/japanesestreet.png" alt="">
                </div>
                <div class="item">
                    <img src="public/images/balaclava.png" alt="">
                </div>
                <div class="item">
                    <img src="public/images/light.png" alt="">
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
                <h2 style="margin-left: 85px;"">PROMOÇÕES</h2>
                <i class=" fa-solid fa-money-bills"></i>
            </div>
            <div class="secImgEsq">
                <img src="public/images/promos.jpg" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção de mais vendidos -->
        <section>
            <div class="sectionDir">
                <h2>MAIS VENDIDOS</h2>
                <i class="fa-solid fa-fire"></i>
            </div>
            <div class="secImgDir">
                <img src="public/images/travis.jpg" alt="imagem promocional">
            </div>
        </section>

        <!-- Seção de recomendados -->
        <section>
            <div class="sectionEsq">
                <h2>RECOMENDADOS</h2>
                <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            <div class="secImgEsq">
                <img src="public/images/mahdi.jpg" alt="imagem promocional">
            </div>
        </section>


        <!-- sessão de categorias -->
        <section class="categorias">
            <h2>CATEGORIAS</h2>

            <div class="coisas">

                <!-- camisas -->
                <span class="camisa">
                    <a href="#" class="grid" id="camisa">
                        <img src="public/images/cams.jpg" alt="imagem da sessão de camisas">
                    </a>
                </span>

                <!-- acessórios -->
                <span class="acess">
                    <a href="#" class="grid" id="acess">
                        <img src="public/images/bone.jpg" alt="imagem da sessão de acessórios">
                    </a>
                </span>

                <!-- sapatos -->
                <span class="sapa">
                    <a href="#" class="grid" id="sapa">
                        <img src="public/images/ekin.jpg" alt="imagem da sessão de sapatos">
                    </a>
                </span>

                <!-- calças -->
                <span class="calca">
                    <a href="#" class="grid" id="calca">
                        <img src="public/images/robot.jpg" alt="imagem da sessão de calças">
                    </a>
                </span>
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

<script src="js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('.search-bar input');
    const fechar = document.querySelector('.search-bar .fechar');
    const lupa = document.querySelector('.icons .pesquisar');
    const icon = document.querySelector('.search-bar .search-icon');

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