/**
 * ============================================================
 * MÓDULO: Gerenciamento de Carrinho de Compras
 * ============================================================
 * 
 * Propósito:
 * Interface JavaScript para gerenciar carrinho de compras e checkout.
 * Comunica com APIs PHP para persistir dados no MySQL.
 * 
 * Funcionalidades:
 * - Listar itens do carrinho
 * - Adicionar/remover itens
 * - Atualizar quantidades
 * - Calcular subtotal e total com frete
 * - Selecionar endereço de entrega
 * - Finalizar pedido (Mercado Pago)
 * 
 * Arquitetura:
 * - State management local (state e enderecoState)
 * - Comunicação via Fetch API (REST)
 * - Manipulação do DOM (vanilla JavaScript)
 * - Event-driven (listeners para botões e formulários)
 * 
 * Dependências:
 * - APIs PHP: adicionar.php, listar.php, atualizar.php, remover.php
 * - APIs Checkout: criar_pedido.php, enderecos.php
 * - Variáveis globais injetadas pelo PHP (window.CARRINHO_API, etc)
 */

// ===== FUNÇÃO UTILITÁRIA: FORMATAÇÃO DE VALORES =====
/**
 * Formata números para padrão brasileiro (R$).
 * 
 * Conversões:
 * - 10.5 → "10,50"
 * - 1234.99 → "1234,99"
 * - "15" → "15,00"
 * 
 * Uso:
 * `R$ ${formatBR(produto.preco)}`
 * 
 * @param {number|string} value - Valor a formatar
 * @returns {string} - Valor formatado (ex: "123,45")
 */
const formatBR = (value) => {
  const number = typeof value === "number" ? value : Number(value) || 0;
  return number.toFixed(2).replace(".", ",");
};

// ===== CONFIGURAÇÕES E VARIÁVEIS GLOBAIS =====
/**
 * Variáveis injetadas pelo PHP no HTML via <script>.
 * 
 * Exemplo de injeção PHP:
 * <script>
 *   window.CARRINHO_API = {
 *     adicionar: '/api/carrinho/adicionar.php',
 *     listar: '/api/carrinho/listar.php',
 *     atualizar: '/api/carrinho/atualizar.php',
 *     remover: '/api/carrinho/remover.php'
 *   };
 *   window.CARRINHO_IS_AUTHENTICATED = <?= isset($_SESSION['logged_in']) ? 'true' : 'false' ?>;
 * </script>
 * 
 * Fallback: {} ou "" caso variáveis não existam (previne erros).
 */
const CART_API = window.CARRINHO_API || {};
const CHECKOUT_API = window.CHECKOUT_API || {};
const LOGIN_URL = window.CARRINHO_LOGIN_URL || "/";
const ADD_ADDRESS_URL = window.CHECKOUT_ADD_ADDRESS_URL || "";
const isAuthenticated = Boolean(window.CARRINHO_IS_AUTHENTICATED);

// ===== STATE MANAGEMENT: CARRINHO =====
/**
 * Estado local do carrinho (sincronizado com backend).
 * 
 * Estrutura:
 * state.itens = [
 *   {
 *     id: 1,              // CarID (item no carrinho)
 *     produtoId: 10,      // RoupaId (produto)
 *     nome: "Camiseta",
 *     imagem: "url...",
 *     quantidade: 2,
 *     tamanho: "M",
 *     precoUnitario: 89.90,
 *     total: 179.80       // quantidade × preçoUnitario
 *   }
 * ]
 * state.subtotal = 179.80 // Soma de todos os totais
 * 
 * Atualização:
 * Sempre que API retorna dados, state é sincronizado.
 */
const state = {
  itens: [],
  subtotal: 0,
};

// ===== STATE MANAGEMENT: ENDEREÇOS DE ENTREGA =====
/**
 * Estado dos endereços cadastrados do usuário.
 * 
 * Estrutura:
 * enderecoState = {
 *   carregado: false,     // True após buscar endereços da API
 *   itens: [...],         // Lista de endereços
 *   selecionado: null     // ID do endereço escolhido (EndEntId)
 * }
 * 
 * Fluxo:
 * 1. Usuário clica "Finalizar Compra"
 * 2. carregarEnderecos() busca da API
 * 3. enderecoState.carregado = true
 * 4. Usuário seleciona endereço
 * 5. enderecoState.selecionado = ID
 * 6. Botão "Ir para Pagamento" habilitado
 */
const enderecoState = {
  carregado: false,
  itens: [],
  selecionado: null,
};

// ===== ESTADO DO FRETE =====
/**
 * Valor do frete selecionado pelo usuário.
 * 
 * Fluxo:
 * 1. Usuário consulta CEP (API Correios/Melhor Envio)
 * 2. Escolhe modalidade (PAC, SEDEX, etc)
 * 3. freteSelecionado recebe valor
 * 4. Total = subtotal + freteSelecionado
 * 
 * Nota: Implementação completa de API de frete não incluída neste TCC.
 * Valor fixo ou calculado manualmente pelo usuário.
 */
let freteSelecionado = 0;

// ===== REFERÊNCIAS DOS ELEMENTOS HTML - TELA DE CARRINHO =====
/**
 * Elementos principais da interface do carrinho.
 * 
 * HTML esperado:
 * <div id="lista-carrinho"><!-- Itens renderizados aqui --></div>
 * <span id="subtotal">0,00</span>
 * <span id="frete-valor">0,00</span>
 * <span id="total">0,00</span>
 * <button id="btn-finalizar">Finalizar Compra</button>
 */
const listaCarrinho = document.getElementById("lista-carrinho");
const subtotalEl = document.getElementById("subtotal");
const freteEl = document.getElementById("frete-valor");
const totalEl = document.getElementById("total");
const btnFinalizar = document.getElementById("btn-finalizar");

// ===== REFERÊNCIAS DOS ELEMENTOS HTML - TELAS DO CHECKOUT =====
/**
 * Sistema de telas para fluxo de checkout.
 * 
 * Fluxo de navegação:
 * 1. telaCarrinho (lista de itens) → visível por padrão
 * 2. telaEnderecos (seleção de endereço) → ao clicar "Finalizar"
 * 3. telaProcessando (loading) → ao criar pedido no Mercado Pago
 * 4. Redirecionamento → para URL de pagamento MP
 * 
 * Controle:
 * Mostrar/ocultar via classList.add/remove('d-none') ou style.display.
 */
const telaCarrinho = document.getElementById("tela-carrinho");
const telaEnderecos = document.getElementById("tela-enderecos");
const telaProcessando = document.getElementById("tela-processando");
const listaEnderecosEl = document.getElementById("lista-enderecos");
const btnVoltarCarrinho = document.getElementById("btn-voltar-carrinho");
const btnIrPagamento = document.getElementById("btn-ir-pagamento");
const btnCancelarProcessamento = document.getElementById("btn-cancelar-processamento");

// ===== FUNÇÃO UTILITÁRIA: SANITIZAÇÃO HTML =====
/**
 * Previne ataques XSS (Cross-Site Scripting).
 * 
 * Conversões:
 * - & → &amp;
 * - < → &lt;
 * - > → &gt;
 * - " → &quot;
 * - ' → &#039;
 * 
 * Uso crítico:
 * Sempre que inserir dados do usuário ou API no HTML via innerHTML.
 * 
 * Exemplo de ataque prevenido:
 * Nome do produto: <script>alert('XSS')</script>
 * Sem escape: executa script malicioso
 * Com escape: exibe texto literal
 * 
 * @param {string} value - String a sanitizar
 * @returns {string} - String segura para HTML
 */
const escapeHtml = (value = "") =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

// ===== FUNÇÃO UTILITÁRIA: NOTIFICAÇÕES TOAST =====
/**
 * Exibe notificação temporária no canto inferior direito.
 * 
 * Estilo:
 * - Fundo dourado (#d4af37) - cor tema IMPERIUM
 * - Texto escuro (#111)
 * - Animação: fade in + fade out após 2.5s
 * - z-index alto para sobrepor tudo
 * 
 * Uso:
 * mostrarAviso("Item adicionado ao carrinho!");
 * mostrarAviso("Erro ao processar pagamento");
 * 
 * Alternativa:
 * Biblioteca externa como Toastify.js, SweetAlert2.
 * Implementação própria evita dependências.
 * 
 * @param {string} mensagem - Texto da notificação
 */
const mostrarAviso = (mensagem) => {
  const aviso = document.createElement("div");
  aviso.textContent = mensagem;
  aviso.style.position = "fixed";
  aviso.style.bottom = "24px";
  aviso.style.right = "24px";
  aviso.style.background = "#d4af37"; // Dourado IMPERIUM
  aviso.style.color = "#111";
  aviso.style.padding = "12px 18px";
  aviso.style.borderRadius = "10px";
  aviso.style.fontWeight = "600";
  aviso.style.zIndex = "99999";
  aviso.style.boxShadow = "0 12px 24px rgba(0,0,0,0.25)";
  document.body.appendChild(aviso);
  
  // Remove automaticamente após 2.5 segundos
  setTimeout(() => aviso.remove(), 2500);
};

// ===== FUNÇÃO: EXIBIR MENSAGEM NA LISTA DE CARRINHO =====
/**
 * Exibe mensagem de estado na área do carrinho.
 * 
 * Usado para:
 * - "Carregando itens do carrinho..."
 * - "Seu carrinho ainda está vazio."
 * - Mensagens de erro (com estilo diferenciado)
 * 
 * @param {string} mensagem - Texto a exibir
 * @param {boolean} isError - Se true, aplica classe estado-erro (cor vermelha)
 */
const setListaMensagem = (mensagem, isError = false) => {
  if (!listaCarrinho) {
    return;
  }
  listaCarrinho.innerHTML = `<p class="estado-lista${
    isError ? " estado-erro" : ""
  }">${escapeHtml(mensagem)}</p>`;
};

// ===== FUNÇÃO: EXIBIR MENSAGEM NA LISTA DE ENDEREÇOS =====
/**
 * Exibe mensagem de estado na área de seleção de endereços.
 * 
 * Comportamento:
 * - Limpa seleção de endereço (enderecoState.selecionado = null)
 * - Desabilita botão "Ir para Pagamento"
 * 
 * Usado para:
 * - "Carregando endereços..."
 * - "Nenhum endereço encontrado."
 * - Mensagens de erro ao buscar endereços
 * 
 * @param {string} mensagem - Texto a exibir
 * @param {boolean} isError - Se true, aplica estilo de erro
 */
const setEnderecoMensagem = (mensagem, isError = false) => {
  if (!listaEnderecosEl) {
    return;
  }
  listaEnderecosEl.innerHTML = `<p class="estado-lista${
    isError ? " estado-erro" : ""
  }">${escapeHtml(mensagem)}</p>`;
  enderecoState.selecionado = null;
  habilitarPagamentoSePossivel();
};

// ===== FUNÇÃO: CONTROLAR BOTÃO DE PAGAMENTO =====
/**
 * Habilita/desabilita botão "Ir para Pagamento" baseado em endereço selecionado.
 * 
 * Regra:
 * - Botão habilitado: enderecoState.selecionado > 0 (ID válido)
 * - Botão desabilitado: nenhum endereço selecionado
 * 
 * Chamado por:
 * - renderizarEnderecos() - após renderizar lista
 * - setEnderecoMensagem() - ao exibir erro
 * - Listener de change nos radios de endereço
 */
const habilitarPagamentoSePossivel = () => {
  if (!btnIrPagamento) {
    return;
  }
  btnIrPagamento.disabled = !(enderecoState.selecionado > 0);
};

// ===== FUNÇÃO: CONSTRUIR HTML DE ENDEREÇO =====
/**
 * Cria markup HTML de um card de endereço.
 * 
 * Estrutura:
 * <label class="endereco-card">
 *   <input type="radio" name="endereco-selecionado" value="{id}">
 *   <div>
 *     <strong>{referência}</strong>
 *     <p>{rua, número - bairro, cidade - UF, CEP}</p>
 *   </div>
 * </label>
 * 
 * Compatívelcom múltiplos formatos de API:
 * - endereco.id ou endereco.EndEntId
 * - endereco.rua ou endereco.EndEntRua
 * - etc (nomes do MySQL)
 * 
 * @param {Object} endereco - Dados do endereço
 * @returns {string} - HTML do card
 */
const buildEnderecoMarkup = (endereco) => {
  const id = Number(endereco.id || endereco.EndEntId || 0);
  const referencia = escapeHtml(
    endereco.referencia || endereco.label || endereco.EndEntRef || "Endereço"
  );
  const rua = endereco.rua || endereco.EndEntRua || "";
  const numero = endereco.numero || endereco.EndEntNum || "";
  const bairro = endereco.bairro || endereco.EndEntBairro || "";
  const cidade = endereco.cidade || endereco.EndEntCid || "";
  const estado = endereco.estado || endereco.EndEntEst || "";
  const cep = endereco.cep || endereco.EndEntCep || "";
  const complemento = endereco.complemento || endereco.EndEntComple || "";
  const descricao = `${rua}, ${numero} - ${bairro}, ${cidade} - ${estado}, CEP: ${cep}${
    complemento ? " - " + complemento : ""
  }`;

  return `
    <label class="endereco-card">
      <input type="radio" name="endereco-selecionado" value="${id}">
      <div class="endereco-card__info">
        <strong>${referencia}</strong>
        <p>${escapeHtml(descricao)}</p>
      </div>
    </label>
  `;
};

// ===== FUNÇÃO: RENDERIZAR LISTA DE ENDEREÇOS =====
/**
 * Renderiza cards de endereços de entrega com radios de seleção.
 * 
 * Comportamento:
 * 1. Se lista vazia:
 *    - Exibe mensagem "Nenhum endereço encontrado"
 *    - Inclui link para cadastrar novo (se ADD_ADDRESS_URL existir)
 *    - Desabilita botão de pagamento
 * 
 * 2. Se lista com itens:
 *    - Renderiza todos os endereços como radios
 *    - Pré-seleciona o primeiro da lista
 *    - Habilita botão de pagamento
 * 
 * @param {Array} enderecos - Lista de endereços da API
 */
const renderizarEnderecos = (enderecos) => {
  if (!listaEnderecosEl) {
    return;
  }

  // ===== CASO: LISTA VAZIA =====
  if (!enderecos.length) {
    if (ADD_ADDRESS_URL) {
      // Inclui link para página de cadastro de endereços
      const safeUrl = escapeHtml(String(ADD_ADDRESS_URL));
      listaEnderecosEl.innerHTML = `
        <p class="estado-lista">
          Nenhum endereço encontrado. <a href="${safeUrl}" target="_blank" rel="noopener noreferrer">Cadastre um novo</a> para continuar.
        </p>`;
    } else {
      setEnderecoMensagem("Nenhum endereço encontrado. Cadastre um novo para continuar.");
    }
    enderecoState.selecionado = null;
    habilitarPagamentoSePossivel();
    return;
  }

  // ===== CASO: RENDERIZAR ENDEREÇOS =====
  /**
   * Cria HTML de todos os endereços e injeta no DOM.
   * map().join('') é mais performante que loop com appendChild.
   */
  listaEnderecosEl.innerHTML = enderecos.map((item) => buildEnderecoMarkup(item)).join("");
  
  // Pré-seleciona primeiro endereço
  enderecoState.selecionado = Number(enderecos[0].id || enderecos[0].EndEntId || 0) || null;
  const firstRadio = listaEnderecosEl.querySelector('input[name="endereco-selecionado"]');
  if (firstRadio && enderecoState.selecionado) {
    firstRadio.checked = true;
  }
  
  habilitarPagamentoSePossivel();
};

// ===== FUNÇÃO: ATUALIZAR TOTAIS DO CARRINHO =====
/**
 * Atualiza valores exibidos de subtotal, frete e total.
 * 
 * Cálculos:
 * - Subtotal: soma dos preços de todos os itens (do backend)
 * - Frete: valor selecionado pelo usuário (consulta CEP)
 * - Total: subtotal + frete
 * 
 * Elementos atualizados:
 * - #subtotal: R$ XX,XX
 * - #frete-valor: R$ XX,XX
 * - #total: R$ XX,XX
 * 
 * Chamado por:
 * - aplicarSnapshot() - após carregar/atualizar carrinho
 * - consultarFrete() - após calcular frete
 */
const atualizarTotais = () => {
  if (subtotalEl) {
    subtotalEl.textContent = formatBR(state.subtotal);
  }
  if (freteEl) {
    freteEl.textContent = formatBR(freteSelecionado);
  }
  const total = state.subtotal + freteSelecionado;
  if (totalEl) {
    totalEl.textContent = formatBR(total);
  }
};

// ===== FUNÇÃO: CONTROLAR BOTÃO FINALIZAR COMPRA =====
/**
 * Habilita/desabilita botão "Finalizar Compra".
 * 
 * Condições para habilitar:
 * 1. Carrinho tem pelo menos 1 item (temProduto = true)
 * 2. Usuário está autenticado (isAuthenticated = true)
 * 
 * Se desabilitado:
 * - Carrinho vazio: "Adicione itens ao carrinho"
 * - Não autenticado: clique redireciona para login
 * 
 * Chamado por:
 * - aplicarSnapshot() - após atualizar carrinho
 */
const verificarBotaoFinalizar = () => {
  if (!btnFinalizar) {
    return;
  }
  const temProduto = state.itens.length > 0;
  btnFinalizar.disabled = !(temProduto && isAuthenticated);
};

// ===== FUNÇÃO: APLICAR SNAPSHOT DO CARRINHO =====
/**
 * Sincroniza estado local com dados da API.
 * 
 * Dados esperados da API:
 * {
 *   "sucesso": true,
 *   "itens": [
 *     {
 *       "id": 1,
 *       "produtoId": 10,
 *       "nome": "Camiseta",
 *       "imagem": "url",
 *       "quantidade": 2,
 *       "tamanho": "M",
 *       "precoUnitario": 89.90,
 *       "total": 179.80
 *     }
 *   ],
 *   "subtotal": 179.80
 * }
 * 
 * Fluxo:
 * 1. Atualiza state.itens e state.subtotal
 * 2. Renderiza lista de itens no DOM
 * 3. Atualiza valores de subtotal/frete/total
 * 4. Verifica se botão "Finalizar" deve estar habilitado
 * 
 * Chamado por:
 * - carregarCarrinho() - carregamento inicial
 * - atualizarItem() - após alterar quantidade
 * - removerItem() - após remover item
 * 
 * @param {Object} dados - Resposta da API com itens e subtotal
 */
const aplicarSnapshot = (dados) => {
  // Valida e atualiza estado
  state.itens = Array.isArray(dados.itens) ? dados.itens : [];
  state.subtotal = typeof dados.subtotal === "number" ? dados.subtotal : 0;
  
  // Atualiza interface
  renderizarCarrinho();
  atualizarTotais();
  verificarBotaoFinalizar();
};

const criarItemMarkup = (item) => {
  const quantidade = item.quantidade || 0;
  const precoFormatado = item.precoFormatado || formatBR(item.precoUnitario || 0);
  const totalFormatado =
    item.totalFormatado || formatBR((item.precoUnitario || 0) * quantidade);
  const imagem = escapeHtml(item.imagem || "");
  const nome = escapeHtml(item.nome || "Produto");
  const tamanho = escapeHtml(item.tamanho || "");

  return `
    <div class="item-carrinho">
      <img src="${imagem}" alt="${nome}">
      <div class="item-info">
        <h4>${nome}</h4>
        ${tamanho ? `<p>Tamanho: ${tamanho}</p>` : ""}
        <p>Quantidade: ${quantidade}</p>
        <p>Preço unitário: R$ ${precoFormatado}</p>
        <p>Total: R$ ${totalFormatado}</p>
        <div class="qtd-control" style="margin-top:8px;display:flex;align-items:center;gap:8px;">
          <button type="button" data-cart-action="decremento" data-item-id="${item.id}">-</button>
          <span>${quantidade}</span>
          <button type="button" data-cart-action="incremento" data-item-id="${item.id}">+</button>
          <button type="button" class="remover" data-cart-action="remover" data-item-id="${item.id}">Remover</button>
        </div>
      </div>
    </div>`;
};

const renderizarCarrinho = () => {
  if (!listaCarrinho) {
    return;
  }

  if (!state.itens.length) {
    setListaMensagem("Seu carrinho ainda está vazio.");
    return;
  }

  const markup = state.itens.map((item) => criarItemMarkup(item)).join("");
  listaCarrinho.innerHTML = markup;
};

const requestJson = async (url, options = {}) => {
  const config = { credentials: "same-origin", ...options };
  const headers = { "Content-Type": "application/json", ...(config.headers || {}) };
  config.headers = headers;
  if (config.body && typeof config.body !== "string") {
    config.body = JSON.stringify(config.body);
  }

  const response = await fetch(url, config);
  if (response.status === 401) {
    window.location.href = LOGIN_URL;
    return null;
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch (_) {
    payload = null;
  }

  if (!response.ok || !payload?.sucesso) {
    throw new Error(payload?.mensagem || "Não foi possível processar a solicitação.");
  }

  return payload;
};

const carregarCarrinho = async () => {
  if (!listaCarrinho || !CART_API.listar) {
    return;
  }

  setListaMensagem("Carregando itens do carrinho...");

  try {
    const payload = await requestJson(CART_API.listar, { method: "GET" });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    setListaMensagem(error.message || "Não foi possível carregar o carrinho.", true);
  }
};

const atualizarItem = async (itemId, delta) => {
  if (!CART_API.atualizar) {
    return;
  }
  try {
    const payload = await requestJson(CART_API.atualizar, {
      method: "POST",
      body: { itemId, delta },
    });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    mostrarAviso(error.message || "Falha ao atualizar o item.");
  }
};

const removerItem = async (itemId) => {
  if (!CART_API.remover) {
    return;
  }
  try {
    const payload = await requestJson(CART_API.remover, {
      method: "POST",
      body: { itemId },
    });
    if (!payload) {
      return;
    }
    aplicarSnapshot(payload);
  } catch (error) {
    mostrarAviso(error.message || "Falha ao remover o item.");
  }
};

const carregarEnderecos = async () => {
  if (!listaEnderecosEl || !CHECKOUT_API.enderecos) {
    return;
  }

  setEnderecoMensagem("Carregando endereços...");

  try {
    const payload = await requestJson(CHECKOUT_API.enderecos, { method: "GET" });
    if (!payload) {
      throw new Error("Não foi possível carregar os endereços.");
    }
    const itens = Array.isArray(payload.enderecos) ? payload.enderecos : [];
    enderecoState.itens = itens;
    enderecoState.carregado = true;
    renderizarEnderecos(itens);
  } catch (error) {
    setEnderecoMensagem(error.message || "Não foi possível carregar os endereços.", true);
  }
};

const mostrarTela = (nome) => {
  if (telaCarrinho) {
    telaCarrinho.style.display = nome === "carrinho" ? "block" : "none";
  }
  if (telaEnderecos) {
    telaEnderecos.style.display = nome === "enderecos" ? "block" : "none";
  }
  if (telaProcessando) {
    telaProcessando.style.display = nome === "processando" ? "block" : "none";
  }
};

const irParaEnderecos = () => {
  if (!isAuthenticated) {
    window.location.href = LOGIN_URL;
    return;
  }
  if (!state.itens.length) {
    mostrarAviso("Seu carrinho está vazio.");
    return;
  }
  mostrarTela("enderecos");
  if (!enderecoState.carregado) {
    carregarEnderecos();
  }
};

const iniciarPagamento = async () => {
  if (!CHECKOUT_API.criarPedido) {
    mostrarAviso("Fluxo de pagamento indisponível no momento.");
    return;
  }

  if (!(enderecoState.selecionado > 0)) {
    mostrarAviso("Selecione um endereço para continuar.");
    return;
  }

  mostrarTela("processando");

  try {
    const payload = await requestJson(CHECKOUT_API.criarPedido, {
      method: "POST",
      body: {
        enderecoId: enderecoState.selecionado,
        freteValor: freteSelecionado,
      },
    });

    if (!payload) {
      throw new Error("Não foi possível iniciar o pagamento.");
    }

    if (payload.pagamentoUrl) {
      window.location.href = payload.pagamentoUrl;
      return;
    }

    throw new Error(payload.mensagem || "Não foi possível iniciar o pagamento.");
  } catch (error) {
    mostrarTela("enderecos");
    mostrarAviso(error.message || "Falha ao redirecionar para o pagamento.");
  }
};

// ===== FUNÇÃO: CONSULTAR FRETE POR CEP =====
/**
 * Calcula valor e prazo de frete baseado no CEP de destino.
 * 
 * Fluxo:
 * 1. Valida formato do CEP (8 dígitos)
 * 2. Consulta ViaCEP para obter cidade/UF
 * 3. Calcula frete baseado em tabela de regiões
 * 4. Exibe resultado com valor e prazo
 * 5. Gera mapa do Google Maps com endereço
 * 6. Atualiza total do carrinho
 * 
 * Tabela de frete (simplificada para MVP):
 * - SP Capital: R$ 15,00 (1-2 dias)
 * - Interior SP: R$ 25,00 (2-4 dias)
 * - RJ/MG/PR: R$ 35,00 (3-6 dias)
 * - Outros: R$ 50,00 (5-9 dias)
 * 
 * Nota:
 * Em produção, integrar com API dos Correios ou Melhor Envio.
 * Esta implementação usa valores fixos por região.
 * 
 * APIs utilizadas:
 * - ViaCEP: https://viacep.com.br/ws/{CEP}/json/
 * - Google Maps Embed: iframe com endereço
 */
const consultarFrete = async () => {
  const cepInput = document.getElementById("input-cep");
  const resultado = document.getElementById("resultado-frete");
  const mapa = document.getElementById("mapa-frete");
  if (!cepInput || !resultado || !mapa) {
    return;
  }

  // Remove caracteres não numéricos (ex: "12345-678" -> "12345678")
  const cep = cepInput.value.replace(/\D/g, "");
  resultado.innerHTML = "";
  mapa.innerHTML = "";

  // ===== VALIDAÇÃO DO CEP =====
  if (cep.length !== 8) {
    resultado.innerHTML = "<p>CEP inválido. Digite 8 números.</p>";
    freteSelecionado = 0;
    atualizarTotais();
    return;
  }

  // Feedback visual de loading
  resultado.innerHTML = '<p style="color:#aaa">Consultando...</p>';
  
  try {
    // ===== CONSULTA VIACEP =====
    /**
     * ViaCEP retorna:
     * {
     *   "cep": "01001-000",
     *   "logradouro": "Praça da Sé",
     *   "bairro": "Sé",
     *   "localidade": "São Paulo",
     *   "uf": "SP",
     *   "erro": true // se CEP inválido
     * }
     */
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const data = await response.json();
    
    if (data.erro) {
      resultado.innerHTML = "<p>CEP não encontrado.</p>";
      freteSelecionado = 0;
      atualizarTotais();
      return;
    }

    const logradouro = data.logradouro || "Rua não informada";
    const cidade = data.localidade || "";
    const uf = data.uf || "";

    // ===== CÁLCULO DE FRETE POR REGIÃO =====
    /**
     * Tabela simplificada de frete.
     * 
     * Critérios:
     * - Distância da origem (SP)
     * - Infraestrutura logística da região
     * 
     * Em produção:
     * - Integrar com API dos Correios (PAC, SEDEX)
     * - Considerar peso e dimensões do pacote
     * - Oferecer múltiplas opções de entrega
     */
    let valorFrete = 0;
    let prazo = "";
    
    if (uf === "SP") {
      if (cidade.toLowerCase() === "são paulo" || cidade.toLowerCase() === "sao paulo") {
        valorFrete = 15; // Capital: mais barato e rápido
        prazo = "1 a 2 dias úteis";
      } else {
        valorFrete = 25; // Interior SP
        prazo = "2 a 4 dias úteis";
      }
    } else if (["RJ", "MG", "PR"].includes(uf)) {
      valorFrete = 35; // Sudeste/Sul (exceto SP)
      prazo = "3 a 6 dias úteis";
    } else {
      valorFrete = 50; // Demais regiões
      prazo = "5 a 9 dias úteis";
    }

    freteSelecionado = valorFrete;
    resultado.innerHTML = `
      <p><strong>${escapeHtml(logradouro)}</strong></p>
      <p>${escapeHtml(cidade)} - ${escapeHtml(uf)}</p>
      <p>Frete: <strong>R$ ${formatBR(valorFrete)}</strong></p>
      <p>Prazo: <strong>${prazo}</strong></p>
    `;

    mapa.innerHTML = `
      <iframe width="100%" height="200" frameborder="0" style="border:0"
        src="https://www.google.com/maps?q=${encodeURIComponent(
          `${logradouro}, ${cidade} - ${uf}`
        )}&output=embed"></iframe>
    `;

    atualizarTotais();
  } catch (error) {
    console.error(error);
    resultado.innerHTML = "<p>Erro ao consultar o CEP.</p>";
    freteSelecionado = 0;
    atualizarTotais();
  }
};

const btnCep = document.getElementById("btn-cep");
const inputCep = document.getElementById("input-cep");
if (btnCep) {
  btnCep.addEventListener("click", consultarFrete);
}
if (inputCep) {
  inputCep.addEventListener("keypress", (event) => {
    if (event.key === "Enter") {
      consultarFrete();
    }
  });
}

if (listaCarrinho) {
  listaCarrinho.addEventListener("click", (event) => {
    const button = event.target.closest("[data-cart-action]");
    if (!button) {
      return;
    }
    const itemId = Number(button.dataset.itemId);
    if (!itemId) {
      return;
    }

    const action = button.dataset.cartAction;
    if (action === "incremento") {
      atualizarItem(itemId, 1);
    } else if (action === "decremento") {
      atualizarItem(itemId, -1);
    } else if (action === "remover") {
      removerItem(itemId);
    }
  });
}

if (listaEnderecosEl) {
  listaEnderecosEl.addEventListener("change", (event) => {
    const input = event.target.closest('input[name="endereco-selecionado"]');
    if (!input) {
      return;
    }
    enderecoState.selecionado = Number(input.value) || null;
    habilitarPagamentoSePossivel();
  });
}

if (btnFinalizar) {
  btnFinalizar.addEventListener("click", () => {
    if (!isAuthenticated) {
      window.location.href = LOGIN_URL;
      return;
    }
    irParaEnderecos();
  });
}

if (btnVoltarCarrinho) {
  btnVoltarCarrinho.addEventListener("click", () => {
    mostrarTela("carrinho");
  });
}

if (btnCancelarProcessamento) {
  btnCancelarProcessamento.addEventListener("click", () => {
    mostrarTela("enderecos");
  });
}

if (btnIrPagamento) {
  btnIrPagamento.addEventListener("click", iniciarPagamento);
}

mostrarTela("carrinho");
carregarCarrinho();
