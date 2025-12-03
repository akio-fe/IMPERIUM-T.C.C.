/**
 * ============================================================
 * MÓDULO: Sistema de Filtros e Busca de Produtos
 * ============================================================
 * 
 * Propósito:
 * Implementa filtros de categoria e busca textual de produtos.
 * Atualiza URL com query string para manter estado entre páginas.
 * 
 * Funcionalidades:
 * - Filtros por categoria (calçados, calças, blusas, etc)
 * - Busca textual em tempo real
 * - Sincronização com URL (?filtro=calcados)
 * - Animação de abertura/fechamento da barra de busca
 * - Filtros na sidebar + links de navegação
 * - Estado persistente (URL preserva filtro)
 * 
 * Estrutura HTML esperada:
 * <div class="search-bar">
 *   <input type="text" placeholder="Buscar...">
 *   <button class="fechar">X</button>
 *   <div class="search-icon">🔍</div>
 * </div>
 * <div class="filtros">
 *   <button data-tipo="todos">Todos</button>
 *   <button data-tipo="calcados">Calçados</button>
 * </div>
 * <div class="produto" data-tipo="calcados">
 *   <h3>Tênis Nike</h3>
 * </div>
 */

// ===== INICIALIZAÇÃO: DOCUMENT READY =====

/**
 * Aguarda carregamento completo do DOM antes de executar.
 * Garante que todos os elementos estejam disponíveis.
 */
document.addEventListener('DOMContentLoaded', () => {
      // ===== SELEÇÃO DOS ELEMENTOS DE BUSCA =====
      
      /**
       * Campo de input da barra de busca.
       * Usado para busca textual em tempo real.
       */
      const input = document.querySelector('.search-bar input');
      
      /**
       * Botão "X" para fechar a barra de busca.
       * Também limpa o texto digitado.
       */
      const fechar = document.querySelector('.search-bar .fechar');
      
      /**
       * Ícone de lupa (🔍) que abre a barra de busca.
       * Geralmente no header da página.
       */
      const lupa = document.querySelector('.icons .pesquisar');
      
      /**
       * Ícone de busca dentro da barra (decorativo).
       * Exibido quando barra está aberta.
       */
      const icon = document.querySelector('.search-bar .search-icon');

      // ===== SELEÇÃO DOS ELEMENTOS DE FILTRO =====
      
      /**
       * Botões de filtro (sidebar ou barra de filtros).
       * Cada botão tem atributo data-tipo com categoria.
       */
      const botoesFiltro = document.querySelectorAll('.filtros button');
      
      /**
       * Links de navegação no header com filtros.
       * Alternativa aos botões da sidebar.
       */
      const navFiltroLinks = document.querySelectorAll('header nav a[data-tipo]');
      
      /**
       * Todos os produtos na página.
       * Cada produto tem atributo data-tipo indicando categoria.
       */
      const produtos = document.querySelectorAll('.produto');

      // ===== INICIALIZAÇÃO DO ESTADO DO FILTRO =====
      
      /**
       * Lista branca de filtros válidos.
       * Previne injeção de valores maliciosos via URL.
       */
      const filtrosValidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros'];
      
      /**
       * Lê parâmetros da query string (?filtro=calcados).
       * URLSearchParams facilita acesso a parâmetros.
       */
      const params = new URLSearchParams(window.location.search);
      const filtroURL = params.get('filtro');
      
      /**
       * Valida filtro da URL contra lista branca.
       * null se filtro for inválido ou não existir.
       */
      const filtroInicial = filtrosValidos.includes(filtroURL) ? filtroURL : null;
      
      /**
       * Fallback: busca link ativo no header.
       * Usado se URL não tiver parâmetro ?filtro.
       */
      const filtroNavAtivo = document.querySelector('header nav a[data-tipo].active');
      
      /**
       * Estado atual do filtro (variável mutável).
       * Prioridade: URL > Link ativo > "todos"
       */
      let tipoAtual = filtroInicial || filtroNavAtivo?.dataset.tipo || 'todos';

      // ===== EVENT LISTENERS: BARRA DE BUSCA =====
      
      /**
       * Valida se todos os elementos de busca existem.
       * Previne erros em páginas sem barra de busca.
       */
      if (lupa && input && fechar && icon) {
        // ===== ABRIR BARRA DE BUSCA =====
        
        /**
         * Clique na lupa: expande barra de busca.
         * 
         * Efeito visual:
         * - Input ganha classe "mostrar" (width: 0 → 200px+)
         * - Botão "X" aparece
         * - Lupa some
         * - Ícone de busca aparece dentro da barra
         * - Focus automático no input (usuário pode digitar imediatamente)
         */
        lupa.addEventListener('click', () => {
          input.classList.add('mostrar');
          fechar.style.display = 'inline-block';
          lupa.style.display = 'none';
          icon.style.display = 'block';
          input.focus();
        });

        // ===== FECHAR BARRA DE BUSCA =====
        
        /**
         * Clique no "X": fecha e limpa barra de busca.
         * 
         * Efeito:
         * - Remove classe "mostrar" (colapsa barra)
         * - Esconde botão "X"
         * - Mostra lupa novamente
         * - Esconde ícone interno
         * - Limpa texto digitado
         * - Reseta filtro para "todos" (mostra todos os produtos)
         */
        fechar.addEventListener('click', () => {
          input.classList.remove('mostrar');
          fechar.style.display = 'none';
          lupa.style.display = 'inline-block';
          icon.style.display = 'none';
          input.value = '';
          aplicarFiltro('todos');
        });

        // ===== BUSCA EM TEMPO REAL =====
        
        /**
         * Evento 'input': dispara a cada tecla pressionada.
         * Aplica filtro instantaneamente (sem precisar apertar Enter).
         */
        input.addEventListener('input', () => aplicarFiltro());
      }

      // ===== FUNÇÃO: APLICAR FILTRO =====
      
      /**
       * Filtra produtos por categoria E busca textual.
       * Atualiza URL com query string.
       * 
       * Lógica de filtragem:
       * 1. Produto passa se: (categoria correta OU "todos") E (nome contém busca OU busca vazia)
       * 2. display: block (visível) ou none (oculto)
       * 
       * @param {string} tipo - Categoria a filtrar (padrão: tipoAtual)
       */
      function aplicarFiltro(tipo = tipoAtual) {
        // Atualiza estado global
        tipoAtual = tipo;
        // Atualiza classes CSS dos botões (destaque visual)
        atualizarClassesAtivas(tipoAtual);

        /**
         * Captura termo de busca do input.
         * trim(): remove espaços extras
         * toLowerCase(): busca case-insensitive
         */
        const termoBusca = (input?.value || '').trim().toLowerCase();
        
        // ===== ITERAÇÃO: FILTRA CADA PRODUTO =====
        
        produtos.forEach(produto => {
          /**
           * Extrai categoria do produto (data-tipo).
           * Fallback: "outros" se atributo não existir.
           */
          const tipoProduto = produto.getAttribute('data-tipo') || 'outros';
          
          /**
           * Extrai nome do produto (<h3>).
           * Usado para busca textual.
           */
          const titulo = produto.querySelector('h3').textContent.toLowerCase();
          
          /**
           * Validação 1: Filtro de categoria.
           * Passa se filtro for "todos" OU categoria coincidir.
           */
          const passaFiltroTipo = (tipoAtual === 'todos') || (tipoProduto === tipoAtual);
          
          /**
           * Validação 2: Busca textual.
           * Passa se busca estiver vazia OU título conter termo.
           */
          const passaBusca = termoBusca === '' || titulo.includes(termoBusca);
          
          /**
           * Produto visível apenas se passar ambas validações.
           * display: block (visível) | none (oculto)
           */
          produto.style.display = (passaFiltroTipo && passaBusca) ? 'block' : 'none';
        });

        // ===== ATUALIZAÇÃO DA URL =====
        
        /**
         * Constrói nova URL com parâmetro ?filtro atualizado.
         * 
         * Comportamento:
         * - "todos": remove parâmetro (URL limpa)
         * - Outras categorias: adiciona/atualiza ?filtro=categoria
         * 
         * replaceState: atualiza URL sem recarregar página.
         * Diferença de pushState: não cria nova entrada no histórico.
         */
        const url = new URL(window.location);
        if (tipoAtual === 'todos') {
          url.searchParams.delete('filtro');
        } else {
          url.searchParams.set('filtro', tipoAtual);
        }
        window.history.replaceState({}, '', url);
      }

      // ===== FUNÇÃO: ATUALIZAR CLASSES ATIVAS =====
      
      /**
       * Atualiza destaque visual dos botões/links de filtro.
       * Apenas o filtro ativo recebe classe especial.
       * 
       * @param {string} tipo - Categoria ativa
       */
      function atualizarClassesAtivas(tipo) {
        /**
         * Itera sobre botões da sidebar.
         * Adiciona classe "ativo" apenas no botão correspondente.
         */
        botoesFiltro.forEach(botao => {
          const ehMesmoTipo = botao.dataset.tipo === tipo;
          botao.classList.toggle('ativo', ehMesmoTipo);
        });
        
        /**
         * Itera sobre links do header.
         * Adiciona classe "active" apenas no link correspondente.
         */
        navFiltroLinks.forEach(link => {
          const ehMesmoTipo = link.dataset.tipo === tipo;
          link.classList.toggle('active', ehMesmoTipo);
        });
      }

      // ===== EVENT LISTENERS: BOTÕES DE FILTRO =====
      
      /**
       * Clique em botão da sidebar: aplica filtro correspondente.
       * dataset.tipo contém a categoria (ex: "calcados", "blusas").
       */
      botoesFiltro.forEach(botao => {
        botao.addEventListener('click', () => aplicarFiltro(botao.dataset.tipo));
      });

      // ===== EVENT LISTENERS: LINKS DE NAVEGAÇÃO =====
      
      /**
       * Clique em link do header: aplica filtro correspondente.
       * 
       * preventDefault(): impede navegação padrão (não recarrega página).
       * blur(): remove focus do link (melhor UX, não fica destacado).
       */
      navFiltroLinks.forEach(link => {
        link.addEventListener('click', event => {
          event.preventDefault();
          aplicarFiltro(link.dataset.tipo);
          link.blur();
        });
      });

      // ===== APLICAÇÃO INICIAL DO FILTRO =====
      
      /**
       * Executa filtro ao carregar a página.
       * Usa tipoAtual (determinado pela URL ou link ativo).
       * 
       * Isso garante que produtos sejam filtrados corretamente
       * mesmo quando usuário acessa URL diretamente (?filtro=calcados).
       */
      aplicarFiltro(tipoAtual);
    });