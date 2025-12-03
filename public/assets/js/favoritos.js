/**
 * ============================================================
 * MÓDULO: Gerenciamento de Favoritos
 * ============================================================
 * 
 * Propósito:
 * Interface para visualizar e gerenciar lista de produtos favoritos.
 * Comunica com API PHP para persistir dados no MySQL.
 * 
 * Funcionalidades:
 * - Listar produtos favoritados do usuário
 * - Remover itens da lista de favoritos
 * - Exibir informações do produto (imagem, nome, preço)
 * - Formatar data de adição aos favoritos
 * - Redirecionar para login se não autenticado
 * 
 * Arquitetura:
 * - API RESTful: GET (listar), DELETE (remover)
 * - Autenticação: sessão PHP via cookies
 * - Renderização: DOM manipulation (vanilla JS)
 * - Estado: gerenciado pelo backend (MySQL)
 * 
 * Estrutura de Dados (API Response):
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "produto": {
 *         "id": 1,
 *         "nome": "Camiseta Streetwear",
 *         "imagem": "/storage/models/...",
 *         "valorFormatado": "R$ 89,90",
 *         "link": "/public/pages/shop/produto.php?id=1"
 *       },
 *       "data": "2024-01-15 14:30:00"
 *     }
 *   ]
 * }
 * 
 * Dependências HTML:
 * <div id="favoritosStatus">Status dinâmico</div>
 * <div id="favoritosContainer">Cards dos produtos</div>
 * 
 * Tecnologias:
 * - Fetch API (comunicação com backend)
 * - ES6+ (arrow functions, async/await, template literals)
 * - DOM API (createElement, appendChild)
 */

// ===== RESOLUÇÃO DE CAMINHOS =====
/**
 * Determina caminho base da pasta /public/ dinamicamente.
 * Permite funcionar em diferentes estruturas de pastas.
 * 
 * @returns {string} - Caminho da pasta public (ex: "/imperium/public")
 */
const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

/**
 * URLs e constantes da aplicação:
 * - PUBLIC_ROOT: base da pasta public
 * - FAVORITOS_API_URL: endpoint da API de favoritos
 * - LOGIN_PAGE: página de login (redirecionamento)
 */
const PUBLIC_ROOT = resolvePublicRoot();
const FAVORITOS_API_URL = `${PUBLIC_ROOT}/api/favoritos.php`;
const LOGIN_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;

// ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
/**
 * Elementos da página de favoritos:
 * - statusEl: exibe mensagens de status/erro
 * - containerEl: contém os cards dos produtos
 */
const statusEl = document.getElementById("favoritosStatus");
const containerEl = document.getElementById("favoritosContainer");

// ===== FUNÇÃO: ATUALIZAR STATUS =====
/**
 * Exibe mensagem de status/erro na página.
 * 
 * Uso:
 * setStatus("Carregando favoritos...") // Cor padrão (cinza)
 * setStatus("Erro ao carregar", true) // Cor vermelha
 * 
 * @param {string} message - Mensagem a exibir
 * @param {boolean} isError - Se true, usa cor vermelha (#e74c3c)
 */
const setStatus = (message, isError = false) => {
  if (!statusEl) {
    return;
  }
  statusEl.textContent = message;
  statusEl.style.color = isError ? "#e74c3c" : "var(--link-color, #aaa)";
};

// ===== FUNÇÃO: BUSCAR FAVORITOS DA API =====
/**
 * Busca lista de produtos favoritos do usuário logado.
 * 
 * Endpoint: GET /api/favoritos.php
 * Autenticação: cookies de sessão PHP
 * 
 * Fluxo:
 * 1. Envia requisição GET com credentials (cookies)
 * 2. Backend verifica sessão PHP
 * 3. Retorna lista de favoritos do usuário
 * 
 * Resposta de sucesso:
 * {
 *   "success": true,
 *   "data": [{produto: {...}, data: "..."}]
 * }
 * 
 * Tratamento de erros:
 * - 401: usuário não autenticado (redireciona para login)
 * - 500: erro no servidor
 * - success: false: erro lógico (ex: banco de dados)
 * 
 * @returns {Promise<Array>} - Lista de favoritos
 * @throws {Error} - Com propriedade status=401 se não autenticado
 */
const fetchFavoritos = async () => {
  const response = await fetch(FAVORITOS_API_URL, {
    headers: { Accept: "application/json" },
    credentials: "same-origin", // Envia cookies da sessão PHP
  });
  
  // Verifica se usuário está autenticado
  if (response.status === 401) {
    const payload = await response.json().catch(() => null);
    const msg = payload?.message || "Faça login para ver seus favoritos.";
    throw Object.assign(new Error(msg), { status: 401 });
  }
  
  const payload = await response.json();
  
  // Valida resposta da API
  if (!payload?.success) {
    throw new Error(
      payload?.message || "Não foi possível carregar os favoritos."
    );
  }
  
  return Array.isArray(payload.data) ? payload.data : [];
};

// ===== FUNÇÃO: FORMATAR DATA =====
/**
 * Converte timestamp MySQL para formato brasileiro legível.
 * 
 * Conversões:
 * - "2024-01-15 14:30:00" → "15 de jan. de 2024"
 * - "2024-12-25 09:00:00" → "25 de dez. de 2024"
 * 
 * Tratamento:
 * - Substitui espaço por "T" para compatibilidade com Safari
 * - Retorna valor original se data inválida
 * - Retorna string vazia se valor nulo/undefined
 * 
 * Formato MySQL: YYYY-MM-DD HH:MM:SS
 * Formato saída: DD de MMM de YYYY (mês abreviado)
 * 
 * @param {string} value - Data no formato MySQL
 * @returns {string} - Data formatada em português
 */
const formatDateTime = (value) => {
  if (!value) {
    return "";
  }
  
  // Safari não aceita espaço entre data e hora
  const date = new Date(value.replace(/ /g, "T"));
  
  // Valida se data é válida
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
 * Cria elemento HTML para exibir produto favorito.
 * 
 * Estrutura do card:
 * - Imagem do produto (link clicável)
 * - Nome do produto
 * - Preço formatado (R$)
 * - Data de adição aos favoritos
 * - Botão "Ver produto" (link)
 * - Botão "Remover" (ação)
 * 
 * HTML gerado:
 * <article class="product">
 *   <a href="/pages/shop/produto.php?id=1">
 *     <img src="/storage/models/..." alt="Nome">
 *   </a>
 *   <h3>Nome do Produto</h3>
 *   <p>R$ 89,90</p>
 *   <small>Favoritado em 15 de jan. de 2024</small>
 *   <div class="product__actions">
 *     <a href="...">Ver produto</a>
 *     <button data-produto-id="1">Remover</button>
 *   </div>
 * </article>
 * 
 * @param {Object} item - Dados do favorito (produto + data)
 * @returns {HTMLElement} - Elemento <article> pronto para inserir no DOM
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
 * Renderiza lista de produtos favoritos na página.
 * 
 * Fluxo:
 * 1. Limpa container (remove cards antigos)
 * 2. Verifica se há itens
 * 3. Atualiza mensagem de status com contagem
 * 4. Cria e insere cards no DOM
 * 
 * Casos especiais:
 * - Lista vazia: exibe "Você ainda não favoritou nenhum produto."
 * - 1 item: "1 produto favoritado."
 * - Múltiplos: "5 produtos favoritados."
 * 
 * @param {Array} items - Lista de favoritos da API
 */
const renderFavoritos = (items) => {
  if (!containerEl) {
    return;
  }
  
  // Limpa cards existentes
  containerEl.innerHTML = "";
  
  // Verifica se lista está vazia
  if (!items.length) {
    setStatus("Você ainda não favoritou nenhum produto.");
    return;
  }
  
  // Atualiza status com plural correto
  setStatus(
    `${items.length} produto${items.length === 1 ? "" : "s"} favoritado${
      items.length === 1 ? "" : "s"
    }.`
  );
  
  // Cria e insere cards
  items.forEach((item) => {
    containerEl.appendChild(createCard(item));
  });
};

// ===== FUNÇÃO: REMOVER FAVORITO =====
/**
 * Remove produto da lista de favoritos via API.
 * 
 * Endpoint: DELETE /api/favoritos.php
 * Body: {"produtoId": 1}
 * 
 * Fluxo:
 * 1. Envia DELETE com ID do produto
 * 2. Backend valida sessão PHP
 * 3. Remove registro da tabela Favoritos no MySQL
 * 4. Retorna sucesso/erro
 * 
 * SQL executado no backend:
 * DELETE FROM Favoritos 
 * WHERE UsuId = :userId AND RoupaId = :produtoId
 * 
 * @param {number} produtoId - ID do produto a remover
 * @throws {Error} - Com status=401 se não autenticado
 */
const removerFavorito = async (produtoId) => {
  const response = await fetch(FAVORITOS_API_URL, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ produtoId }),
    credentials: "same-origin", // Envia cookies de sessão
  });
  
  // Verifica autenticação
  if (response.status === 401) {
    throw Object.assign(new Error("Faça login para gerenciar favoritos."), {
      status: 401,
    });
  }
  
  const payload = await response.json();
  
  // Valida sucesso da operação
  if (!payload?.success) {
    throw new Error(payload?.message || "Erro ao remover favorito.");
  }
};

// ===== HANDLER: CLIQUE NO BOTÃO REMOVER =====
/**
 * Gerencia clique em botões de remoção de favoritos.
 * 
 * Event Delegation:
 * Listener único no container, detecta cliques em qualquer botão.
 * Mais eficiente que adicionar listener em cada botão.
 * 
 * Fluxo:
 * 1. Identifica qual botão foi clicado (via data-produto-id)
 * 2. Extrai ID do produto
 * 3. Desabilita botão e altera texto para "Removendo..."
 * 4. Chama API de remoção
 * 5. Remove card do DOM (sem recarregar página)
 * 6. Atualiza contador de favoritos
 * 7. Reabilita botão em caso de erro
 * 
 * Tratamento de erros:
 * - Exibe alert com mensagem
 * - Se 401: redireciona para login
 * 
 * @param {Event} event - Evento de clique do navegador
 */
const handleRemoveClick = async (event) => {
  // Usa closest para suportar clique em elementos filhos
  const button = event.target.closest("[data-produto-id]");
  if (!button) {
    return; // Clique fora de botão
  }
  
  const produtoId = Number(button.dataset.produtoId);
  if (!produtoId) {
    return; // ID inválido
  }
  
  // Feedback visual durante remoção
  button.disabled = true;
  button.textContent = "Removendo...";
  
  try {
    // Remove via API
    await removerFavorito(produtoId);
    
    // Remove card do DOM (atualização instantânea)
    button.closest(".product")?.remove();
    
    // Atualiza status com nova contagem
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
    // Exibe erro ao usuário
    alert(error.message || "Erro ao remover favorito.");
    
    // Redireciona se sessão expirou
    if (error.status === 401) {
      window.location.href = LOGIN_PAGE;
    }
  } finally {
    // Reabilita botão se ainda existir no DOM
    if (button.isConnected) {
      button.disabled = false;
      button.textContent = "Remover";
    }
  }
};

// ===== INICIALIZAÇÃO DA PÁGINA =====
/**
 * Inicializa página de favoritos.
 * 
 * Fluxo:
 * 1. Valida presença dos elementos HTML necessários
 * 2. Exibe "Carregando favoritos..."
 * 3. Busca lista da API
 * 4. Renderiza cards dos produtos
 * 5. Adiciona listener para remoção (event delegation)
 * 
 * Tratamento de erros:
 * - Erro genérico: exibe mensagem em vermelho
 * - 401 (não autenticado): 
 *   - Exibe mensagem de erro
 *   - Cria botão "Fazer login"
 *   - Botão redireciona para página de login
 * 
 * Event Delegation:
 * Um único listener no container para todos os botões.
 * Mais eficiente que adicionar listener individual.
 */
const initFavoritosPage = async () => {
  // Valida elementos HTML
  if (!statusEl || !containerEl) {
    return;
  }
  
  // Exibe loading
  setStatus("Carregando favoritos...");
  
  try {
    // Busca e renderiza favoritos
    const itens = await fetchFavoritos();
    renderFavoritos(itens);
  } catch (error) {
    // Exibe erro
    setStatus(
      error.message || "Não foi possível carregar seus favoritos.",
      true
    );
    
    // Caso especial: não autenticado
    if (error.status === 401) {
      // Cria botão para redirecionar ao login
      const loginButton = document.createElement("button");
      loginButton.textContent = "Fazer login";
      loginButton.className = "remove-button";
      loginButton.addEventListener("click", () => {
        window.location.href = LOGIN_PAGE;
      });
      containerEl.appendChild(loginButton);
    }
  }
  
  // Adiciona listener para remoção (event delegation)
  containerEl.addEventListener("click", handleRemoveClick);
};

// ===== AUTO-INICIALIZAÇÃO =====
/**
 * Aguarda carregamento completo do DOM antes de inicializar.
 * 
 * Garante que elementos HTML (#favoritosStatus, #favoritosContainer)
 * existam antes de tentar acessá-los.
 */
document.addEventListener("DOMContentLoaded", initFavoritosPage);
