/**
 * ============================================================
 * MÓDULO: Gerenciamento de Produtos Favoritos
 * ============================================================
 * 
 * Propósito:
 * Interface para visualizar e remover produtos favoritados pelo usuário.
 * Lista de desejos (wishlist) para compras futuras.
 * 
 * Funcionalidades:
 * - Listar produtos favoritos do usuário autenticado
 * - Remover item da lista de favoritos
 * - Feedback visual de carregamento e erros
 * - Exibição de data de favoritação
 * - Link para página do produto
 * - Redirecionamento para login se não autenticado
 * 
 * Arquitetura:
 * - State management implícito (renderização baseada em API)
 * - Event delegation para botões de remoção
 * - Fetch API para comunicação com backend
 * - DOM manipulation (criação dinâmica de cards)
 * 
 * API Backend:
 * - GET /api/favoritos.php: lista favoritos do usuário
 * - DELETE /api/favoritos.php: remove favorito
 * - Autenticação: sessão PHP (cookies)
 * - Retorna 401 se não autenticado
 * 
 * Banco de Dados (MySQL):
 * Tabela: favoritos
 * - FavId (PK)
 * - CliId (FK → clientes)
 * - RoupaId (FK → roupas)
 * - FavData (timestamp de criação)
 * 
 * HTML esperado:
 * <div id="favoritosStatus"></div>
 * <div id="favoritosContainer"></div>
 * 
 * Tecnologias:
 * - Vanilla JavaScript (ES6+)
 * - Fetch API (async/await)
 * - Template literals (HTML dinâmico)
 */

// ===== FUNÇÃO UTILITÁRIA: RESOLUÇÃO DE CAMINHOS =====
/**
 * Detecta a pasta /public/ na URL e retorna o caminho base.
 * 
 * Cenários:
 * 1. URL: https://site.com/TCC/public/pages/favoritos.html
 *    Retorna: "/TCC/public"
 * 
 * 2. URL: https://site.com/favoritos.html (sem /public/)
 *    Retorna: ""
 * 
 * Uso:
 * - Construir URLs relativas para APIs e páginas
 * - Funciona em diferentes ambientes (dev, prod, subpastas)
 * - Evita hardcoding de caminhos
 * 
 * @returns {string} - Caminho base até /public ou string vazia
 */
const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

// ===== CONSTANTES DE CONFIGURAÇÃO =====
/**
 * URLs usadas pelo módulo.
 * 
 * PUBLIC_ROOT: caminho base da pasta public
 * FAVORITOS_API_URL: endpoint da API de favoritos
 * LOGIN_PAGE: página de login/cadastro
 * 
 * Exemplo de valores:
 * - PUBLIC_ROOT: "/TCC/public"
 * - FAVORITOS_API_URL: "/TCC/public/api/favoritos.php"
 * - LOGIN_PAGE: "/TCC/public/pages/auth/cadastro_login.html"
 */
const PUBLIC_ROOT = resolvePublicRoot();
const FAVORITOS_API_URL = `${PUBLIC_ROOT}/api/favoritos.php`;
const LOGIN_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;

// ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
/**
 * Elementos principais da interface de favoritos.
 * 
 * statusEl: elemento para mensagens de status
 * - Exibe "Carregando...", "X produtos favoritados", erros, etc
 * - Feedback visual para o usuário
 * 
 * containerEl: container para lista de produtos
 * - Cards de produtos renderizados dinamicamente
 * - Vazio = nenhum favorito
 * 
 * HTML esperado:
 * <p id="favoritosStatus">Carregando favoritos...</p>
 * <div id="favoritosContainer"><!-- Cards aqui --></div>
 */
const statusEl = document.getElementById("favoritosStatus");
const containerEl = document.getElementById("favoritosContainer");

// ===== FUNÇÃO: ATUALIZAR STATUS =====
/**
 * Exibe mensagem no elemento de status com cor apropriada.
 * 
 * Parâmetros:
 * - message: texto a exibir
 * - isError: se true, usa cor vermelha para erros
 * 
 * Cores:
 * - Erro: #e74c3c (vermelho)
 * - Normal: variável CSS --link-color ou #aaa (cinza)
 * 
 * Uso:
 * - setStatus("Carregando...", false)
 * - setStatus("Erro ao carregar", true)
 * 
 * @param {string} message - Mensagem a exibir
 * @param {boolean} isError - Se é mensagem de erro
 */
const setStatus = (message, isError = false) => {
  if (!statusEl) {
    return;
  }
  statusEl.textContent = message;
  statusEl.style.color = isError ? "#e74c3c" : "var(--link-color, #aaa)";
};

// ===== FUNÇÃO: BUSCAR FAVORITOS NA API =====
/**
 * Requisita lista de favoritos do usuário autenticado.
 * 
 * Endpoint: GET /api/favoritos.php
 * 
 * Headers:
 * - Accept: application/json (indica que espera JSON)
 * - credentials: same-origin (inclui cookies de sessão)
 * 
 * Resposta esperada (sucesso):
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "produto": {
 *         "id": 10,
 *         "nome": "Camiseta Premium",
 *         "imagem": "/uploads/produto.jpg",
 *         "link": "/produto.php?id=10",
 *         "valorFormatado": "R$ 89,90"
 *       },
 *       "data": "2024-12-01 10:30:00"
 *     }
 *   ]
 * }
 * 
 * Resposta esperada (erro):
 * {
 *   "success": false,
 *   "message": "Erro ao buscar favoritos"
 * }
 * 
 * Status HTTP:
 * - 200: sucesso
 * - 401: não autenticado (sem sessão válida)
 * - 500: erro interno do servidor
 * 
 * Tratamento de Erros:
 * - 401: lança erro com propriedade status (para redirecionar ao login)
 * - Outros: lança erro genérico
 * 
 * @returns {Promise<Array>} - Array de objetos de favoritos
 * @throws {Error} - Se requisição falhar ou usuário não autenticado
 */
const fetchFavoritos = async () => {
  const response = await fetch(FAVORITOS_API_URL, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  
  // ===== TRATAMENTO: NÃO AUTENTICADO =====
  /**
   * Status 401 indica que usuário não está logado.
   * 
   * Ação:
   * - Tenta extrair mensagem do JSON de resposta
   * - Cria erro com propriedade status (usado em handleRemoveClick)
   * - Permite código chamador redirecionar para login
   */
  if (response.status === 401) {
    const payload = await response.json().catch(() => null);
    const msg = payload?.message || "Faça login para ver seus favoritos.";
    throw Object.assign(new Error(msg), { status: 401 });
  }
  
  const payload = await response.json();
  
  // ===== VALIDAÇÃO DA RESPOSTA =====
  /**
   * Verifica se backend retornou sucesso.
   * 
   * Campo success:
   * - true: operação bem-sucedida
   * - false: erro (banco de dados, permissões, etc)
   */
  if (!payload?.success) {
    throw new Error(
      payload?.message || "Não foi possível carregar os favoritos."
    );
  }
  
  // Retorna array de favoritos ou array vazio como fallback
  return Array.isArray(payload.data) ? payload.data : [];
};

// ===== FUNÇÃO: FORMATAR DATA E HORA =====
/**
 * Converte timestamp do banco para formato legível em português.
 * 
 * Entrada esperada:
 * - "2024-12-01 10:30:00" (formato MySQL DATETIME)
 * 
 * Saída:
 * - "01 de dez. de 2024"
 * 
 * Processo:
 * 1. Substitui espaço por "T" (padrão ISO 8601)
 * 2. Cria objeto Date
 * 3. Valida se data é válida
 * 4. Formata usando Intl.DateTimeFormat (padrão pt-BR)
 * 
 * Opções de formatação:
 * - day: "2-digit" (01, 02, ..., 31)
 * - month: "short" (jan, fev, mar, ...)
 * - year: "numeric" (2024)
 * 
 * Fallback:
 * - Se data inválida, retorna valor original
 * - Se valor vazio/null, retorna string vazia
 * 
 * @param {string} value - Timestamp do MySQL
 * @returns {string} - Data formatada em português
 */
const formatDateTime = (value) => {
  if (!value) {
    return "";
  }
  // Converte formato MySQL para ISO 8601 (necessário para Safari)
  const date = new Date(value.replace(/ /g, "T"));
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

// ===== FUNÇÃO: CRIAR CARD DE PRODUTO =====
/**
 * Gera elemento HTML (card) para um produto favoritado.
 * 
 * Estrutura do item:
 * {
 *   produto: {
 *     id: 10,
 *     nome: "Camiseta Premium",
 *     imagem: "/uploads/produto.jpg",
 *     link: "/produto.php?id=10",
 *     valorFormatado: "R$ 89,90"
 *   },
 *   data: "2024-12-01 10:30:00"
 * }
 * 
 * HTML gerado:
 * <article class="product">
 *   <a href="/produto.php?id=10" class="product__image-link">
 *     <img src="/uploads/produto.jpg" alt="Camiseta Premium">
 *   </a>
 *   <h3>Camiseta Premium</h3>
 *   <p>R$ 89,90</p>
 *   <small>Favoritado em 01 de dez. de 2024</small>
 *   <div class="product__actions">
 *     <a class="product__view" href="/produto.php?id=10">Ver produto</a>
 *     <button class="remove-button" data-produto-id="10">Remover</button>
 *   </div>
 * </article>
 * 
 * Classes CSS:
 * - product: estilo do card
 * - product__image-link: link clicável da imagem
 * - product__actions: container de botões de ação
 * - product__view: botão de visualizar produto
 * - remove-button: botão de remover favorito
 * 
 * Data Attribute:
 * - data-produto-id: ID do produto (usado em handleRemoveClick)
 * 
 * @param {Object} item - Objeto de favorito da API
 * @returns {HTMLElement} - Elemento <article> do card
 */
const createCard = (item) => {
  const article = document.createElement("article");
  article.className = "product";
  article.innerHTML = `
		<a href="${item.produto.link}" class="product__image-link">
			<img src="${item.produto.imagem}" alt="${item.produto.nome}">
		</a>
		<h3>${item.produto.nome}</h3>
		<p>${item.produto.valorFormatado}</p>
		<small>Favoritado em ${formatDateTime(item.data)}</small>
		<div class="product__actions">
			<a class="product__view" href="${item.produto.link}">Ver produto</a>
			<button class="remove-button" data-produto-id="${
        item.produto.id
      }">Remover</button>
		</div>`;
  return article;
};

// ===== FUNÇÃO: RENDERIZAR LISTA DE FAVORITOS =====
/**
 * Exibe produtos favoritados na interface.
 * 
 * Comportamento:
 * - Lista vazia: exibe mensagem "Você ainda não favoritou nenhum produto"
 * - Lista com itens: renderiza cards e atualiza contador
 * 
 * Processo:
 * 1. Limpa container (remove cards antigos)
 * 2. Verifica se lista está vazia
 * 3. Atualiza status com quantidade de produtos
 * 4. Itera sobre itens e cria cards
 * 5. Adiciona cada card ao container
 * 
 * Pluralização:
 * - 1 produto: "1 produto favoritado"
 * - 2+ produtos: "2 produtos favoritados"
 * 
 * @param {Array} items - Array de objetos de favoritos
 */
const renderFavoritos = (items) => {
  if (!containerEl) {
    return;
  }
  
  // Limpa conteúdo anterior
  containerEl.innerHTML = "";
  
  // ===== CASO: LISTA VAZIA =====
  if (!items.length) {
    setStatus("Você ainda não favoritou nenhum produto.");
    return;
  }
  
  // ===== CASO: LISTA COM ITENS =====
  // Atualiza status com contador
  setStatus(
    `${items.length} produto${items.length === 1 ? "" : "s"} favoritado${
      items.length === 1 ? "" : "s"
    }.`
  );
  
  // Renderiza cada produto como card
  items.forEach((item) => {
    containerEl.appendChild(createCard(item));
  });
};

// ===== FUNÇÃO: REMOVER FAVORITO (API) =====
/**
 * Envia requisição DELETE para remover favorito do banco.
 * 
 * Endpoint: DELETE /api/favoritos.php
 * 
 * Headers:
 * - Content-Type: application/json
 * - credentials: same-origin (cookies de sessão)
 * 
 * Body:
 * {
 *   "produtoId": 10
 * }
 * 
 * Backend:
 * - Valida sessão do usuário
 * - Verifica se favorito pertence ao usuário
 * - Executa DELETE na tabela favoritos
 * - Retorna confirmação
 * 
 * Resposta esperada:
 * {
 *   "success": true,
 *   "message": "Favorito removido com sucesso"
 * }
 * 
 * Tratamento de Erros:
 * - 401: usuário não autenticado (sessão expirou)
 * - success: false: erro no banco de dados ou favorito não existe
 * 
 * @param {number} produtoId - ID do produto a remover
 * @throws {Error} - Se remoção falhar
 */
const removerFavorito = async (produtoId) => {
  const response = await fetch(FAVORITOS_API_URL, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ produtoId }),
    credentials: "same-origin",
  });
  
  // ===== TRATAMENTO: NÃO AUTENTICADO =====
  if (response.status === 401) {
    throw Object.assign(new Error("Faça login para gerenciar favoritos."), {
      status: 401,
    });
  }
  
  const payload = await response.json();
  
  // ===== VALIDAÇÃO DA RESPOSTA =====
  if (!payload?.success) {
    throw new Error(payload?.message || "Erro ao remover favorito.");
  }
};

// ===== FUNÇÃO: HANDLER DE CLIQUE EM REMOVER =====
/**
 * Processa clique no botão "Remover" de um card de favorito.
 * 
 * Event Delegation:
 * - Listener único no container (não em cada botão)
 * - Usa closest() para encontrar botão clicado
 * - Eficiente: menos listeners na memória
 * 
 * Fluxo:
 * 1. Identifica botão clicado via data-produto-id
 * 2. Extrai ID do produto
 * 3. Desabilita botão (previne cliques duplicados)
 * 4. Altera texto para "Removendo..."
 * 5. Chama API para remover
 * 6. Remove card do DOM
 * 7. Atualiza contador de produtos
 * 8. Em caso de erro, exibe alerta e restaura botão
 * 
 * UX:
 * - Feedback visual imediato (botão desabilitado)
 * - Remoção otimista (remove do DOM antes de confirmar)
 * - Em caso de erro, alerta usuário mas não restaura card
 * 
 * Tratamento de Erros:
 * - 401: redireciona para login
 * - Outros: exibe alert com mensagem
 * 
 * @param {Event} event - Evento de clique
 */
const handleRemoveClick = async (event) => {
  // ===== IDENTIFICAÇÃO DO BOTÃO =====
  /**
   * Usa closest() para subir na árvore DOM até encontrar botão.
   * 
   * Motivo:
   * - event.target pode ser span interno do botão
   * - closest() garante encontrar o botão independente de estrutura
   */
  const button = event.target.closest("[data-produto-id]");
  if (!button) {
    return;
  }
  
  // ===== EXTRAÇÃO DO ID DO PRODUTO =====
  const produtoId = Number(button.dataset.produtoId);
  if (!produtoId) {
    return;
  }
  
  // ===== FEEDBACK VISUAL: DESABILITAR BOTÃO =====
  /**
   * Previne múltiplos cliques durante requisição.
   * 
   * Estados do botão:
   * 1. Normal: "Remover" (enabled)
   * 2. Processando: "Removendo..." (disabled)
   * 3. Erro: "Remover" (enabled novamente)
   */
  button.disabled = true;
  button.textContent = "Removendo...";
  
  try {
    // ===== CHAMADA À API =====
    await removerFavorito(produtoId);
    
    // ===== REMOÇÃO DO DOM =====
    /**
     * Remove card da interface após sucesso.
     * 
     * closest(".product"): busca elemento pai com classe "product"
     * ?.remove(): remove elemento se existir (optional chaining)
     * 
     * Remoção otimista:
     * - UI atualiza imediatamente (não espera confirmação visual do backend)
     * - Melhor experiência do usuário
     */
    button.closest(".product")?.remove();
    
    // ===== ATUALIZAÇÃO DO CONTADOR =====
    /**
     * Recalcula quantidade de produtos baseado em cards no DOM.
     * 
     * containerEl.children.length: quantidade de cards restantes
     * 
     * Se 0: exibe mensagem vazia
     * Se > 0: atualiza contador com pluralização correta
     */
    if (!containerEl.children.length) {
      setStatus("Você ainda não favoritou nenhum produto.");
    } else {
      setStatus(
        `${containerEl.children.length} produto${
          containerEl.children.length === 1 ? "" : "s"
        } favoritado${containerEl.children.length === 1 ? "" : "s"}.`
      );
    }
  } catch (error) {
    // ===== TRATAMENTO DE ERROS =====
    /**
     * Exibe alerta e, se 401, redireciona para login.
     * 
     * Nota:
     * - Card não é restaurado (remoção otimista não reverte)
     * - Usuário pode recarregar página se necessário
     */
    alert(error.message || "Erro ao remover favorito.");
    if (error.status === 401) {
      window.location.href = LOGIN_PAGE;
    }
  } finally {
    // ===== RESTAURAÇÃO DO BOTÃO =====
    /**
     * Reabilita botão se ainda estiver no DOM.
     * 
     * isConnected: verifica se elemento ainda está na página
     * - true: elemento no DOM
     * - false: elemento foi removido (sucesso)
     * 
     * Caso de uso:
     * - Erro na requisição: botão volta ao estado normal
     * - Sucesso: botão foi removido junto com card (não executa)
     */
    if (button.isConnected) {
      button.disabled = false;
      button.textContent = "Remover";
    }
  }
};

// ===== FUNÇÃO: INICIALIZAÇÃO DA PÁGINA =====
/**
 * Carrega e exibe favoritos ao abrir a página.
 * 
 * Executado:
 * - No evento DOMContentLoaded (DOM pronto, antes de imagens)
 * 
 * Fluxo:
 * 1. Valida presença de elementos HTML necessários
 * 2. Exibe mensagem "Carregando..."
 * 3. Busca favoritos da API
 * 4. Renderiza produtos na interface
 * 5. Em caso de erro, exibe mensagem e botão de login (se 401)
 * 6. Configura listener para botões de remoção (event delegation)
 * 
 * Tratamento de Erros:
 * - 401: exibe botão "Fazer login" e redireciona ao clicar
 * - Outros: exibe mensagem de erro genérica
 * 
 * Event Delegation:
 * - Listener único no containerEl
 * - Captura cliques em todos os botões "Remover"
 * - Eficiente mesmo com muitos produtos
 */
const initFavoritosPage = async () => {
  // ===== VALIDAÇÃO DE ELEMENTOS =====
  /**
   * Garante que elementos HTML necessários existem.
   * 
   * Early return:
   * - Previne erros se página não tiver estrutura esperada
   * - Permite reutilizar código em diferentes contextos
   */
  if (!statusEl || !containerEl) {
    return;
  }
  
  // ===== FEEDBACK INICIAL =====
  setStatus("Carregando favoritos...");
  
  try {
    // ===== BUSCAR E RENDERIZAR =====
    const itens = await fetchFavoritos();
    renderFavoritos(itens);
  } catch (error) {
    // ===== TRATAMENTO DE ERROS =====
    setStatus(
      error.message || "Não foi possível carregar seus favoritos.",
      true
    );
    
    // ===== CASO: USUÁRIO NÃO AUTENTICADO =====
    /**
     * Se erro 401, exibe botão de login.
     * 
     * Comportamento:
     * - Cria botão dinamicamente
     * - Adiciona ao container
     * - Redireciona ao clicar
     * 
     * UX:
     * - Facilita acesso ao login
     * - Evita usuário ter que navegar manualmente
     */
    if (error.status === 401) {
      const loginButton = document.createElement("button");
      loginButton.textContent = "Fazer login";
      loginButton.className = "remove-button";
      loginButton.addEventListener("click", () => {
        window.location.href = LOGIN_PAGE;
      });
      containerEl.appendChild(loginButton);
    }
  }
  
  // ===== EVENT DELEGATION: LISTENER ÚNICO =====
  /**
   * Configura listener no container para capturar cliques em botões.
   * 
   * Vantagens:
   * - 1 listener ao invés de N (onde N = quantidade de produtos)
   * - Funciona com elementos adicionados dinamicamente
   * - Melhor performance e uso de memória
   * 
   * Funcionamento:
   * - Clique em qualquer lugar do container dispara evento
   * - handleRemoveClick verifica se clique foi em botão de remoção
   * - Processa apenas se data-produto-id presente
   */
  containerEl.addEventListener("click", handleRemoveClick);
};

// ===== INICIALIZAÇÃO AUTOMÁTICA =====
/**
 * Aguarda DOM carregar completamente antes de executar.
 * 
 * DOMContentLoaded:
 * - Dispara quando HTML foi parseado completamente
 * - Não espera imagens, CSS ou outros recursos externos
 * - Mais rápido que 'load' event
 * 
 * Motivo:
 * - Garante que elementos HTML (statusEl, containerEl) existam
 * - Previne erros de referência nula
 */
document.addEventListener("DOMContentLoaded", initFavoritosPage);
