/**
 * ============================================================
 * MÓDULO: Filtro e Busca de Produtos
 * ============================================================
 * 
 * Propósito:
 * Sistema de filtragem e busca para catálogo de produtos.
 * Permite usuários encontrar produtos por categoria e nome.
 * 
 * Funcionalidades:
 * - Filtro por categoria (calçados, calças, blusas, camisas, conjuntos, outros)
 * - Busca por nome de produto (campo de texto)
 * - Combinação de filtros (categoria + busca)
 * - Barra de busca expansível (animação)
 * - Sincronização com URL (query string ?filtro=categoria)
 * - Destaque visual de filtro ativo
 * - Suporte a navegação no header e botões de filtro
 * 
 * Arquitetura:
 * - Client-side filtering (sem requisições ao servidor)
 * - DOM manipulation (show/hide produtos)
 * - URL state management (history.replaceState)
 * - Event-driven (listeners para cliques e digitação)
 * 
 * HTML esperado:
 * <div class="search-bar">
 *   <input type="text">
 *   <span class="search-icon">🔍</span>
 *   <span class="fechar">✕</span>
 * </div>
 * <div class="icons">
 *   <button class="pesquisar">🔍</button>
 * </div>
 * <div class="filtros">
 *   <button data-tipo="todos">Todos</button>
 *   <button data-tipo="calcados">Calçados</button>
 *   ...
 * </div>
 * <header>
 *   <nav>
 *     <a data-tipo="todos">Todos</a>
 *     <a data-tipo="calcados">Calçados</a>
 *     ...
 *   </nav>
 * </header>
 * <div class="produto" data-tipo="calcados">
 *   <h3>Tênis Nike</h3>
 * </div>
 * 
 * Data Attributes:
 * - data-tipo: categoria do produto ou filtro
 * - data-tipo="todos": exibe todos os produtos
 * 
 * Tecnologias:
 * - Vanilla JavaScript (ES6+)
 * - History API (URL manipulation)
 * - CSS classes dinâmicas (show/hide)
 */

// ===== INICIALIZAÇÃO: CONFIGURAÇÃO DO SISTEMA DE FILTROS =====
/**
 * Event listener executado quando DOM carrega completamente.
 * 
 * Responsabilidade:
 * - Buscar elementos HTML necessários
 * - Configurar listeners de eventos
 * - Determinar filtro inicial (URL ou padrão)
 * - Aplicar filtro inicial
 * 
 * DOMContentLoaded:
 * - Garante que elementos HTML existem antes de acessá-los
 * - Mais rápido que 'load' (não espera imagens)
 */
document.addEventListener('DOMContentLoaded', () => {
  // ===== REFERÊNCIAS DOS ELEMENTOS HTML - BARRA DE BUSCA =====
  /**
   * Elementos da barra de busca expansível.
   * 
   * input: campo de texto para busca por nome
   * fechar: botão "X" para fechar busca e limpar filtros
   * lupa: botão de lupa (abre busca)
   * icon: ícone de lupa dentro da barra (visual)
   * 
   * Comportamento:
   * - Busca inicialmente oculta (CSS)
   * - Lupa exibe busca com animação
   * - Fechar oculta busca e reseta filtros
   */
  const input = document.querySelector('.search-bar input');
  const fechar = document.querySelector('.search-bar .fechar');
  const lupa = document.querySelector('.icons .pesquisar');
  const icon = document.querySelector('.search-bar .search-icon');

  // ===== REFERÊNCIAS DOS ELEMENTOS HTML - FILTROS E PRODUTOS =====
  /**
   * Elementos do sistema de filtragem.
   * 
   * botoesFiltro: botões de categoria (calçados, calças, etc)
   * navFiltroLinks: links no header (mesma função que botões)
   * produtos: todos os cards de produto na página
   * 
   * Seletores:
   * - '.filtros button': botões na seção de filtros
   * - 'header nav a[data-tipo]': links com atributo data-tipo
   * - '.produto': cards de produto
   */
  const botoesFiltro = document.querySelectorAll('.filtros button');
  const navFiltroLinks = document.querySelectorAll('header nav a[data-tipo]');
  const produtos = document.querySelectorAll('.produto');

  // ===== CONFIGURAÇÃO: CATEGORIAS VÁLIDAS =====
  /**
   * Lista de categorias aceitas pelo sistema.
   * 
   * Categorias:
   * - todos: exibe todos os produtos (sem filtro)
   * - calcados: sapatos, tênis, sandálias
   * - calcas: calças, bermudas, shorts
   * - blusas: blusas, camisetas femininas
   * - camisas: camisas, polos masculinas
   * - conjuntos: looks completos (calça + camisa)
   * - outros: acessórios, meias, etc
   * 
   * Uso:
   * - Validar parâmetro ?filtro= na URL
   * - Prevenir injeção de valores inválidos
   */
  const filtrosValidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros'];
  
  // ===== DETERMINAÇÃO DO FILTRO INICIAL =====
  /**
   * Define qual categoria deve estar ativa ao carregar página.
   * 
   * Prioridade (ordem de verificação):
   * 1. Parâmetro URL (?filtro=calcados)
   * 2. Link ativo no header (class="active")
   * 3. Padrão: "todos"
   * 
   * Exemplo de URL:
   * https://site.com/produtos.html?filtro=calcados
   * → tipoAtual = "calcados"
   * 
   * Validação:
   * - filtroURL verificado contra filtrosValidos
   * - Previne valores arbitrários na URL
   */
  const params = new URLSearchParams(window.location.search);
  const filtroURL = params.get('filtro');
  const filtroInicial = filtrosValidos.includes(filtroURL) ? filtroURL : null;
  const filtroNavAtivo = document.querySelector('header nav a[data-tipo].active');
  let tipoAtual = filtroInicial || filtroNavAtivo?.dataset.tipo || 'todos';

  // ===== CONFIGURAÇÃO: BARRA DE BUSCA EXPANSÍVEL =====
  /**
   * Listeners para animação de abrir/fechar busca.
   * 
   * Validação:
   * - Só configura se todos os elementos existirem
   * - Previne erros em páginas sem busca
   */
  if (lupa && input && fechar && icon) {
    // ===== EVENTO: ABRIR BUSCA =====
    /**
     * Clique na lupa expande barra de busca.
     * 
     * Ações:
     * 1. Adiciona classe 'mostrar' ao input (CSS transition)
     * 2. Exibe botão fechar
     * 3. Oculta lupa (substitui por fechar)
     * 4. Exibe ícone dentro da barra
     * 5. Foca no input (cursor pronto para digitação)
     * 
     * UX:
     * - Animação suave via CSS
     * - Input pronto para uso imediato
     */
    lupa.addEventListener('click', () => {
      input.classList.add('mostrar');
      fechar.style.display = 'inline-block';
      lupa.style.display = 'none';
      icon.style.display = 'block';
      input.focus();
    });

    // ===== EVENTO: FECHAR BUSCA =====
    /**
     * Clique no "X" recolhe barra e reseta filtros.
     * 
     * Ações:
     * 1. Remove classe 'mostrar' (CSS transition reversa)
     * 2. Oculta botão fechar
     * 3. Exibe lupa novamente
     * 4. Oculta ícone interno
     * 5. Limpa texto do input
     * 6. Reseta filtro para "todos"
     * 
     * UX:
     * - Volta ao estado inicial
     * - Limpa busca e filtros automaticamente
     */
    fechar.addEventListener('click', () => {
      input.classList.remove('mostrar');
      fechar.style.display = 'none';
      lupa.style.display = 'inline-block';
      icon.style.display = 'none';
      input.value = '';
      aplicarFiltro('todos');
    });

    // ===== EVENTO: DIGITAÇÃO NO INPUT =====
    /**
     * Cada tecla digitada reaplica filtros.
     * 
     * Comportamento:
     * - Filtragem em tempo real (live search)
     * - Combina categoria + termo de busca
     * - Não requer botão "Buscar"
     * 
     * Performance:
     * - Executado a cada tecla (pode ser otimizado com debounce)
     * - OK para pequenas quantidades de produtos (<100)
     */
    input.addEventListener('input', () => aplicarFiltro());
  }

  // ===== FUNÇÃO PRINCIPAL: APLICAR FILTROS =====
  /**
   * Filtra produtos baseado em categoria e termo de busca.
   * 
   * Lógica de Filtragem:
   * 1. Atualiza tipo atual (categoria)
   * 2. Atualiza classes visuais dos filtros
   * 3. Obtém termo de busca (normalizado)
   * 4. Itera sobre cada produto:
   *    a. Verifica se passa no filtro de categoria
   *    b. Verifica se passa no filtro de busca (nome)
   *    c. Mostra/oculta produto baseado nos dois critérios
   * 5. Atualiza URL com categoria selecionada
   * 
   * Combinação de Filtros:
   * - Produto visível se: (categoria correta OU "todos") E (busca vazia OU nome contém termo)
   * - Operador lógico: (categoria && busca) = produto visível
   * 
   * Exemplo:
   * - Filtro: "calçados" + Busca: "nike"
   * - Resultado: apenas calçados com "nike" no nome
   * 
   * @param {string} tipo - Categoria selecionada (padrão: tipoAtual)
   */
  function aplicarFiltro(tipo = tipoAtual) {
    // Atualiza estado global
    tipoAtual = tipo;
    
    // Atualiza visual dos botões/links ativos
    atualizarClassesAtivas(tipoAtual);

    // ===== OBTENÇÃO DO TERMO DE BUSCA =====
    /**
     * Normaliza texto digitado pelo usuário.
     * 
     * Transformações:
     * - .trim(): remove espaços no início/fim
     * - .toLowerCase(): converte para minúsculas
     * 
     * Motivo:
     * - Busca case-insensitive ("Nike" = "nike")
     * - Ignora espaços acidentais
     */
    const termoBusca = (input?.value || '').trim().toLowerCase();
    
    // ===== ITERAÇÃO E FILTRAGEM =====
    /**
     * Processa cada produto individualmente.
     */
    produtos.forEach(produto => {
      // ===== EXTRAÇÃO DE DADOS DO PRODUTO =====
      /**
       * Obtém categoria e nome do produto.
       * 
       * tipoProduto: valor de data-tipo do elemento
       * - Ex: <div class="produto" data-tipo="calcados">
       * - Fallback: "outros" se não especificado
       * 
       * titulo: texto do <h3> (nome do produto)
       * - Normalizado para lowercase (busca case-insensitive)
       */
      const tipoProduto = produto.getAttribute('data-tipo') || 'outros';
      const titulo = produto.querySelector('h3').textContent.toLowerCase();
      
      // ===== AVALIAÇÃO DOS CRITÉRIOS =====
      /**
       * Verifica se produto passa em cada filtro.
       * 
       * passaFiltroTipo:
       * - true se: tipo é "todos" OU tipo do produto coincide
       * - Ex: tipoAtual="calcados" && tipoProduto="calcados" → true
       * - Ex: tipoAtual="todos" && tipoProduto=qualquer → true
       * 
       * passaBusca:
       * - true se: busca vazia OU nome contém termo
       * - Ex: termoBusca="" → true (sempre passa)
       * - Ex: termoBusca="nike" && titulo="Tênis Nike Air" → true
       * - Ex: termoBusca="adidas" && titulo="Tênis Nike Air" → false
       */
      const passaFiltroTipo = (tipoAtual === 'todos') || (tipoProduto === tipoAtual);
      const passaBusca = termoBusca === '' || titulo.includes(termoBusca);
      
      // ===== APLICAÇÃO DA VISIBILIDADE =====
      /**
       * Mostra/oculta produto baseado nos critérios.
       * 
       * Lógica AND:
       * - Produto visível SOMENTE se passar nos dois filtros
       * - display: 'block' (visível)
       * - display: 'none' (oculto)
       * 
       * CSS alternativo:
       * - Poderia usar classes: .hidden { display: none !important; }
       * - Implementação atual usa inline styles para simplicidade
       */
      produto.style.display = (passaFiltroTipo && passaBusca) ? 'block' : 'none';
    });

    // ===== ATUALIZAÇÃO DA URL =====
    /**
     * Sincroniza URL com filtro ativo (sem recarregar página).
     * 
     * History API:
     * - replaceState: altera URL sem adicionar ao histórico
     * - Usuário pode copiar URL e compartilhar filtro ativo
     * 
     * Query String:
     * - tipo="todos": remove ?filtro= da URL (estado padrão)
     * - tipo="calcados": adiciona/atualiza ?filtro=calcados
     * 
     * Exemplos:
     * - todos: https://site.com/produtos.html
     * - calçados: https://site.com/produtos.html?filtro=calcados
     * 
     * Benefício:
     * - URL bookmarkable (usuário pode salvar filtro)
     * - Refresh mantém filtro ativo
     * - Facilita compartilhamento
     */
    const url = new URL(window.location);
    if (tipoAtual === 'todos') {
      url.searchParams.delete('filtro');
    } else {
      url.searchParams.set('filtro', tipoAtual);
    }
    window.history.replaceState({}, '', url);
  }

  // ===== FUNÇÃO: ATUALIZAR CLASSES VISUAIS =====
  /**
   * Destaca filtro/link ativo visualmente.
   * 
   * Classes CSS:
   * - 'ativo': para botões de filtro
   * - 'active': para links no header
   * 
   * Comportamento:
   * - Remove classes de todos os elementos
   * - Adiciona classe apenas ao elemento ativo
   * 
   * Visual típico:
   * - Ativo: cor dourada, negrito, sublinhado
   * - Inativo: cor padrão, peso normal
   * 
   * @param {string} tipo - Categoria ativa
   */
  function atualizarClassesAtivas(tipo) {
    // ===== ATUALIZAÇÃO DOS BOTÕES DE FILTRO =====
    /**
     * Itera sobre botões e aplica/remove classe 'ativo'.
     * 
     * classList.toggle(classe, condição):
     * - Adiciona classe se condição true
     * - Remove classe se condição false
     */
    botoesFiltro.forEach(botao => {
      const ehMesmoTipo = botao.dataset.tipo === tipo;
      botao.classList.toggle('ativo', ehMesmoTipo);
    });
    
    // ===== ATUALIZAÇÃO DOS LINKS NO HEADER =====
    /**
     * Mesma lógica para links de navegação.
     * 
     * Uso:
     * - Mantém consistência visual entre botões e header
     * - Usuário vê claramente qual categoria está ativa
     */
    navFiltroLinks.forEach(link => {
      const ehMesmoTipo = link.dataset.tipo === tipo;
      link.classList.toggle('active', ehMesmoTipo);
    });
  }

  // ===== CONFIGURAÇÃO: LISTENERS DOS BOTÕES DE FILTRO =====
  /**
   * Configura clique em cada botão de categoria.
   * 
   * Comportamento:
   * - Clique no botão aplica filtro correspondente
   * - Ex: clique em "Calçados" → aplicarFiltro('calcados')
   * 
   * Data Attribute:
   * - data-tipo: categoria do botão
   * - Lido via botao.dataset.tipo
   */
  botoesFiltro.forEach(botao => {
    botao.addEventListener('click', () => aplicarFiltro(botao.dataset.tipo));
  });

  // ===== CONFIGURAÇÃO: LISTENERS DOS LINKS NO HEADER =====
  /**
   * Configura clique em links de navegação.
   * 
   * Comportamento:
   * - Previne navegação padrão (preventDefault)
   * - Aplica filtro sem recarregar página
   * - Remove foco do link (blur) após clicar
   * 
   * event.preventDefault():
   * - Impede href do link de navegar
   * - Mantém usuário na mesma página
   * - JavaScript controla comportamento
   * 
   * link.blur():
   * - Remove foco visual do link
   * - Evita link ficar com estilo de foco após uso
   */
  navFiltroLinks.forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      aplicarFiltro(link.dataset.tipo);
      link.blur();
    });
  });

  // ===== APLICAÇÃO DO FILTRO INICIAL =====
  /**
   * Executa filtragem ao carregar página.
   * 
   * Motivo:
   * - Aplica filtro da URL (se existir)
   * - Ou aplica filtro padrão ("todos")
   * - Garante produtos corretos visíveis desde o início
   * 
   * Sem esta linha:
   * - Todos os produtos apareceriam inicialmente
   * - Mesmo com ?filtro= na URL
   */
  aplicarFiltro(tipoAtual);
});