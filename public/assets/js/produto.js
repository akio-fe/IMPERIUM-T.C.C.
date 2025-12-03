/**
 * ============================================================
 * MÓDULO: Visualização de Produto com 3D
 * ============================================================
 * 
 * Propósito:
 * Interface completa da página de produto com visualizador 3D.
 * Gerencia visualização interativa, favoritos e adição ao carrinho.
 * 
 * Funcionalidades Principais:
 * - Visualizador 3D com Three.js (WebGL)
 * - Rotação e zoom do modelo 3D
 * - Sistema de favoritos (integração com API)
 * - Adição ao carrinho (localStorage)
 * - Seleção de tamanhos
 * - Alternância de visualização (conjuntos: superior/inferior/completo)
 * - Busca de produtos (barra de pesquisa)
 * 
 * Arquitetura 3D:
 * - Three.js 0.129.0 (WebGL rendering)
 * - GLTFLoader (carregamento de modelos .gltf/.glb)
 * - OrbitControls (controle de câmera)
 * - Sistema de fallback para erros (placeholder estático)
 * - Proteção contra crash de GPU (ImageBitmap disable)
 * 
 * Estrutura de Dados do Produto:
 * window.produto = {
 *   id: 1,
 *   nome: "Camiseta Streetwear",
 *   preco: 89.90,
 *   imagem: "/storage/models/...",
 *   modelPath: "/storage/models/produto.glb",
 *   categoriaId: 2, // 1=Calçados, 2=Camisetas, 5/11=Conjuntos
 *   favorito: true,
 *   isAuthenticated: true
 * }
 * 
 * Estrutura HTML Esperada:
 * <div class="container-produto" data-produto='{"id":1,...}'>
 *   <div id="container3D"><!-- Canvas Three.js injetado aqui --></div>
 *   <div class="tamanhos"><!-- Botões de tamanho --></div>
 *   <input type="checkbox" id="btn-favoritar">
 *   <button id="btn-add-cart">Adicionar ao Carrinho</button>
 *   <div class="btn-parte" data-parte="superior">Superior</div>
 *   <div class="btn-parte" data-parte="inferior">Inferior</div>
 *   <div class="btn-parte" data-parte="completo">Completo</div>
 * </div>
 * 
 * Tecnologias:
 * - Three.js 0.129.0 (3D rendering)
 * - GLTFLoader (formato de modelo 3D padrão)
 * - OrbitControls (interação com câmera)
 * - Fetch API (favoritos)
 * - LocalStorage (carrinho)
 * 
 * Segurança:
 * - Sanitização de URLs de modelos
 * - Validação de categoria para conjuntos
 * - Proteção contra erros de GPU (WebGL out of memory)
 * - Sistema de cooldown após crashes
 */

// ===== IMPORTAÇÕES THREE.JS =====
/**
 * Bibliotecas WebGL para renderização 3D.
 * 
 * Componentes:
 * - THREE: namespace principal (Scene, Camera, Renderer, etc)
 * - GLTFLoader: carregador de modelos glTF/GLB (formato padrão web)
 * - OrbitControls: controle de câmera orbital (rotação, zoom, pan)
 * 
 * CDN: Skypack (ESM compatível)
 * Versão: 0.129.0 (estável)
 */
import * as THREE from 'https://cdn.skypack.dev/three@0.129.0';
import { GLTFLoader } from 'https://cdn.skypack.dev/three@0.129.0/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'https://cdn.skypack.dev/three@0.129.0/examples/jsm/controls/OrbitControls.js';

/**
 * Expõe THREE globalmente para compatibilidade com scripts legados.
 * Alguns plugins podem esperar window.THREE disponível.
 */
window.THREE = THREE;

// ===== RESOLUÇÃO DE CAMINHOS =====
/**
 * Determina caminho base da pasta /public/ dinamicamente.
 * 
 * @returns {string} - Caminho da pasta public
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
 * Constantes da aplicação:
 * - PUBLIC_ROOT: base da pasta public (/imperium/public)
 * - CARRINHO_KEY: chave do localStorage para carrinho
 * - FAVORITOS_API_URL: endpoint para gerenciar favoritos
 * - LOGIN_PAGE: página de login/cadastro
 * - LOGIN_POPUP_ID: ID do modal de login
 */
const PUBLIC_ROOT = resolvePublicRoot();
const CARRINHO_KEY = "carrinho";
const FAVORITOS_API_URL = `${PUBLIC_ROOT}/api/favoritos.php`;
const LOGIN_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;
const LOGIN_POPUP_ID = "login-required-popup";

// ===== VARIÁVEL GLOBAL: MODELO 3D =====
/**
 * Armazena referência ao modelo 3D carregado.
 * 
 * Usado para:
 * - Alternância entre partes de conjuntos (superior/inferior/completo)
 * - Limpeza de recursos ao trocar modelos
 * - Prevenção de vazamento de memória
 * 
 * Tipo: THREE.Object3D | null
 */
let currentModel = null;

// ===== FUNÇÃO: MODAL DE LOGIN =====
/**
 * Factory function (IIFE) que cria modal de login sob demanda.
 * 
 * Padrão Singleton:
 * - Cria modal apenas uma vez
 * - Retorna referência em chamadas subsequentes
 * 
 * Uso:
 * const popup = ensureLoginPopup();
 * popup.classList.remove('hidden'); // Exibe modal
 * 
 * Estilo injetado dinamicamente via <style>.
 * Modal é overlay fullscreen com card centralizado.
 * 
 * @returns {HTMLElement} - Elemento do modal
 */
const ensureLoginPopup = (() => {
  let created = false;
  return () => {
    // Retorna modal existente se já foi criado
    if (created) {
      return document.getElementById(LOGIN_POPUP_ID);
    }
    
    // Cria estilos CSS do modal
    const style = document.createElement("style");
    style.textContent = `
      #${LOGIN_POPUP_ID} {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 20px;
      }
      #${LOGIN_POPUP_ID}.hidden { display: none; }
      #${LOGIN_POPUP_ID} .login-popup__card {
        background: #111;
        color: #f5f5f5;
        border: 1px solid #333;
        border-radius: 12px;
        max-width: 420px;
        width: 100%;
        padding: 32px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.4);
        font-family: "Inter", sans-serif;
      }
      body.light #${LOGIN_POPUP_ID} .login-popup__card {
        background: #fff;
        color: #111;
        border-color: #ddd;
      }
      #${LOGIN_POPUP_ID} .login-popup__title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 12px;
      }
      #${LOGIN_POPUP_ID} .login-popup__text {
        font-size: 0.95rem;
        line-height: 1.4;
        margin-bottom: 24px;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions button,
      #${LOGIN_POPUP_ID} .login-popup__actions a {
        border: 0;
        border-radius: 999px;
        padding: 10px 18px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.95rem;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__cancel {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
      }
      body.light #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__cancel {
        color: #111;
        border-color: #111;
      }
      #${LOGIN_POPUP_ID} .login-popup__actions .login-popup__confirm {
        background: #d4af37;
        color: #111;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 140px;
      }
      #${LOGIN_POPUP_ID} .login-popup__close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: transparent;
        border: 0;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
      }
    `;
    document.head.appendChild(style);

    // ===== CRIAÇÃO DO MODAL HTML =====
    /**
     * Estrutura do modal:
     * - Overlay escuro (rgba backdrop)
     * - Card centralizado com:
     *   - Botão fechar (X)
     *   - Título
     *   - Mensagem explicativa
     *   - Ações: "Agora não" e "Fazer login"
     */
    const overlay = document.createElement("div");
    overlay.id = LOGIN_POPUP_ID;
    overlay.className = "hidden";
    overlay.innerHTML = `
      <div class="login-popup__card">
        <button type="button" class="login-popup__close" aria-label="Fechar">×</button>
        <h2 class="login-popup__title">Faça login para favoritar</h2>
        <p class="login-popup__text">
          Você precisa estar logado para salvar itens nos favoritos.
        </p>
        <div class="login-popup__actions">
          <button type="button" class="login-popup__cancel">Agora não</button>
          <a class="login-popup__confirm" href="${LOGIN_PAGE}">Fazer login</a>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    // ===== LISTENERS DO MODAL =====
    /**
     * Três formas de fechar:
     * 1. Clique fora do card (no overlay)
     * 2. Botão X (close)
     * 3. Botão "Agora não"
     */
    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        overlay.classList.add("hidden");
      }
    });
    overlay.querySelector(".login-popup__close").addEventListener("click", () => {
      overlay.classList.add("hidden");
    });
    overlay.querySelector(".login-popup__cancel").addEventListener("click", () => {
      overlay.classList.add("hidden");
    });

    created = true;
    return overlay;
  };
})();

/**
 * Exibe modal de login.
 * Wrapper simplificado para ensureLoginPopup().
 */
const showLoginPopup = () => {
  const overlay = ensureLoginPopup();
  overlay.classList.remove("hidden");
};

// ===== FUNÇÃO: PARSEAR DADOS DO PRODUTO =====
/**
 * Extrai dados do produto do atributo data-produto no HTML.
 * 
 * HTML esperado:
 * <div class="container-produto" data-produto='{"id":1,"nome":"...",...}'>
 * 
 * Dados injetados pelo PHP:
 * $produtoJson = json_encode($produto);
 * echo "<div data-produto='{$produtoJson}'>";
 * 
 * @returns {Object|null} - Dados do produto ou null se inválido
 */
const parseProdutoData = () => {
  const container = document.querySelector(".container-produto");
  if (!container || !container.dataset.produto) {
    return null;
  }
  try {
    return JSON.parse(container.dataset.produto);
  } catch (error) {
    console.error("Produto inválido:", error);
    return null;
  }
};

// ===== FUNÇÃO: INICIALIZAR BARRA DE BUSCA =====
/**
 * Configura animação de expansão da barra de pesquisa.
 * 
 * Comportamento:
 * - Clique na lupa: expande input de busca
 * - Clique no X: colapsa e limpa input
 * 
 * HTML esperado:
 * <div class="search-bar">
 *   <input type="text">
 *   <span class="search-icon">🔍</span>
 *   <span class="fechar">×</span>
 * </div>
 * <div class="icons">
 *   <span class="pesquisar">🔍</span>
 * </div>
 */
const initSearchBar = () => {
  const input = document.querySelector(".search-bar input");
  const fechar = document.querySelector(".search-bar .fechar");
  const lupa = document.querySelector(".icons .pesquisar");
  const icon = document.querySelector(".search-bar .search-icon");

  if (!input || !fechar || !lupa || !icon) {
    return;
  }

  // Expandir barra ao clicar na lupa
  lupa.addEventListener("click", () => {
    input.classList.add("mostrar");
    fechar.style.display = "inline-block";
    lupa.style.display = "none";
    icon.style.display = "block";
    input.focus();
  });

  // Colapsar e limpar ao clicar no X
  fechar.addEventListener("click", () => {
    input.classList.remove("mostrar");
    fechar.style.display = "none";
    lupa.style.display = "inline-block";
    icon.style.display = "none";
    input.value = "";
  });
};

// ===== FUNÇÃO: INICIALIZAR SELETOR DE TAMANHOS =====
/**
 * Renderiza botões de tamanho baseado na categoria do produto.
 * 
 * Categorias:
 * - 1 (Calçados): tamanhos numéricos (38-44)
 * - Outros: tamanhos alfabéticos (PP, P, M, G, GG, XGG)
 * 
 * HTML gerado:
 * <div class="tamanhos">
 *   <button class="tamanho-btn" data-tamanho="M">M</button>
 *   ...
 * </div>
 * 
 * Comportamento:
 * Apenas um tamanho pode ser selecionado por vez (classe 'selected').
 * 
 * @param {Object} produto - Dados do produto com categoriaId
 */
const initTamanhos = (produto) => {
  const container = document.querySelector(".tamanhos");
  if (!container) {
    return;
  }

  // Define os tamanhos baseado na categoria do produto
  let tamanhos = [];
  if (produto && produto.categoriaId === 1) {
    // Calçados: tamanhos numéricos
    tamanhos = ['38', '39', '40', '41', '42', '43', '44'];
  } else {
    // Outras categorias: tamanhos alfabéticos
    tamanhos = ['PP', 'P', 'M', 'G', 'GG', 'XGG'];
  }

  // Limpa o container e adiciona os botões
  container.innerHTML = '';
  tamanhos.forEach((tamanho) => {
    const botao = document.createElement('button');
    botao.type = 'button';
    botao.textContent = tamanho;
    botao.addEventListener('click', () => {
      container.querySelectorAll('button').forEach((item) => item.classList.remove('selected'));
      botao.classList.add('selected');
    });
    container.appendChild(botao);
  });
};

// ===== FUNÇÕES DE LOCALSTORAGE =====
/**
 * Lê dados do localStorage com tratamento de erro.
 * 
 * @param {string} key - Chave do localStorage
 * @returns {Array} - Array parseado ou [] se erro
 */
const readStorage = (key) => {
  try {
    return JSON.parse(localStorage.getItem(key)) || [];
  } catch (_) {
    return [];
  }
};

/**
 * Salva dados no localStorage como JSON.
 * 
 * @param {string} key - Chave do localStorage
 * @param {*} value - Valor a serializar (geralmente Array ou Object)
 */
const writeStorage = (key, value) => {
  localStorage.setItem(key, JSON.stringify(value));
};

// ===== FUNÇÃO: PARSEAR RESPOSTA DA API =====
/**
 * Converte resposta HTTP em objeto JSON com tratamento de erros.
 * 
 * Tratamento:
 * - Se JSON inválido: retorna null
 * - Se resposta não-OK (4xx, 5xx): lança erro com status e mensagem
 * 
 * @param {Response} response - Resposta do fetch
 * @returns {Promise<Object>} - Payload JSON
 * @throws {Error} - Com propriedade status se resposta não-OK
 */
const parseApiResponse = async (response) => {
  let payload = null;
  try {
    payload = await response.json();
  } catch (_) {
    payload = null;
  }
  if (!response.ok) {
    const message = payload?.message || "Não foi possível atualizar favoritos.";
    const error = new Error(message);
    error.status = response.status;
    throw error;
  }
  return payload;
};

// ===== FUNÇÃO: ATUALIZAR FAVORITO =====
/**
 * Adiciona ou remove produto dos favoritos via API.
 * 
 * Método HTTP:
 * - POST: adiciona favorito (shouldFavorite=true)
 * - DELETE: remove favorito (shouldFavorite=false)
 * 
 * Endpoint: /api/favoritos.php
 * Body: {"produtoId": 123}
 * 
 * @param {number} produtoId - ID do produto
 * @param {boolean} shouldFavorite - True para adicionar, false para remover
 * @returns {Promise<Object>} - Resposta da API
 */
const updateFavorite = async (produtoId, shouldFavorite) => {
  const response = await fetch(FAVORITOS_API_URL, {
    method: shouldFavorite ? "POST" : "DELETE",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ produtoId }),
    credentials: "same-origin",
  });
  return parseApiResponse(response);
};

// ===== SISTEMA DE PROTEÇÃO: ESTABILIDADE DO VIEWER =====
/**
 * Previne loops infinitos de crashes do visualizador 3D.
 * 
 * Mecanismo:
 * - Após erro crítico (out of memory, context lost), ativa cooldown
 * - Durante cooldown (5 minutos), viewer não tenta carregar
 * - Exibe placeholder estático no lugar
 * 
 * Uso:
 * if (viewerStabilityGuard.isDisabled()) {
 *   // Exibir placeholder
 *   return;
 * }
 * 
 * Em caso de erro:
 * viewerStabilityGuard.triggerCooldown();
 */
const viewerStabilityGuard = (() => {
  const COOLDOWN_MS = 5 * 60 * 1000; // 5 minutos
  let disabledUntil = 0;
  return {
    isDisabled() {
      return Date.now() < disabledUntil;
    },
    triggerCooldown() {
      disabledUntil = Date.now() + COOLDOWN_MS;
    },
  };
})();

// ===== SISTEMA DE PROTEÇÃO: IMAGE BITMAP =====
/**
 * Desabilita temporariamente window.createImageBitmap.
 * 
 * Contexto:
 * GLTFLoader do Three.js tenta usar ImageBitmap para carregar texturas.
 * Em GPUs limitadas, isso causa erro "could not be allocated".
 * 
 * Solução:
 * Desabilitar ImageBitmap força Three.js a usar fallback (HTMLImageElement).
 * 
 * Sistema de lock:
 * - Múltiplas chamadas incrementam contador
 * - Função retorna callback de release
 * - ImageBitmap é restaurado quando lockCount chega a 0
 * 
 * Uso:
 * const release = imageBitmapToggle.disable();
 * // ... carrega modelo 3D ...
 * release(); // Restaura ImageBitmap
 * 
 * @returns {Function} - Callback para restaurar createImageBitmap
 */
const imageBitmapToggle = (() => {
  let lockCount = 0;
  let originalCreateImageBitmap = null;
  return {
    disable() {
      if (typeof window === "undefined") {
        return () => {};
      }
      if (
        typeof window.createImageBitmap === "undefined" &&
        !originalCreateImageBitmap
      ) {
        return () => {};
      }
      // Salva referência original e desabilita
      if (!lockCount) {
        originalCreateImageBitmap =
          originalCreateImageBitmap || window.createImageBitmap || null;
        if (typeof window.createImageBitmap !== "undefined") {
          window.createImageBitmap = undefined;
        }
      }
      lockCount += 1;
      
      // Retorna função de release
      return () => {
        if (!lockCount) {
          return;
        }
        lockCount -= 1;
        // Restaura quando todas as locks forem liberadas
        if (!lockCount && originalCreateImageBitmap) {
          window.createImageBitmap = originalCreateImageBitmap;
          originalCreateImageBitmap = null;
        }
      };
    },
  };
})();

// ===== FUNÇÕES DE DETECÇÃO DE ERROS =====
/**
 * Verifica se erro está relacionado a ImageBitmap.
 * Indica que deve tentar novamente com ImageBitmap desabilitado.
 * 
 * @param {Error} error - Erro capturado
 * @returns {boolean} - True se for erro de ImageBitmap
 */
const shouldRetryWithoutImageBitmap = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /ImageBitmap|could not be allocated/i.test(message);
};

/**
 * Verifica se GPU ficou sem memória (out of memory).
 * Indica problema grave - visualizador deve ser desabilitado.
 * 
 * @param {Error} error - Erro capturado
 * @returns {boolean} - True se for out of memory
 */
const isWebGLOutOfMemory = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /out_of_memory|context lost/i.test(message);
};

/**
 * Verifica se WebGL falhou ao inicializar contexto.
 * Pode ocorrer em navegadores sem suporte WebGL ou GPU desabilitada.
 * 
 * @param {Error} error - Erro capturado
 * @returns {boolean} - True se for erro de contexto WebGL
 */
const isWebGLContextInitFailure = (error) => {
  const message =
    error?.message || error?.error?.message || String(error || "");
  return /webgl context|context.*creation/i.test(message);
};

// ===== FUNÇÃO: INICIALIZAR SISTEMA DE FAVORITOS =====
/**
 * Configura toggle de favoritos com integração à API.
 * 
 * Comportamento:
 * - Usuário logado: toggle funciona normalmente (POST/DELETE na API)
 * - Usuário não logado: exibe modal de login ao clicar
 * 
 * HTML esperado:
 * <input type="checkbox" id="btn-favoritar">
 * 
 * Estado inicial:
 * Sincronizado com produto.favorito (vem do backend PHP).
 * 
 * API:
 * - POST /api/favoritos.php: adiciona favorito
 * - DELETE /api/favoritos.php: remove favorito
 * 
 * @param {Object} produto - Dados do produto com id e favorito
 */
const initFavorito = (produto) => {
  if (!produto || !produto.id) {
    return;
  }
  const toggle = document.getElementById("btn-favoritar");
  if (!toggle) {
    return;
  }

  const requiresLogin = !produto.isAuthenticated;
  const setToggle = (value) => {
    toggle.checked = Boolean(value);
  };

  // Sincroniza estado inicial com backend
  setToggle(produto.favorito);

  // Se não logado, exibe modal ao clicar
  if (requiresLogin) {
    toggle.addEventListener("change", () => {
      setToggle(false); // Desfaz toggle
      showLoginPopup();
    });
    return;
  }

  // ===== LISTENER: TOGGLE DE FAVORITOS =====
  /**
   * Gerencia cliques no checkbox de favoritos.
   * 
   * Fluxo:
   * 1. Previne múltiplos cliques (loading state)
   * 2. Desabilita toggle durante requisição
   * 3. Envia POST (adicionar) ou DELETE (remover) para API
   * 4. Atualiza estado local (produto.favorito)
   * 5. Exibe toast de confirmação
   * 6. Reabilita toggle
   * 
   * Tratamento de erro:
   * - Reverte estado do toggle
   * - Exibe mensagem de erro
   * - Se 401: exibe modal de login (sessão expirou)
   */
  toggle.addEventListener("change", async () => {
    // Previne cliques múltiplos
    if (toggle.dataset.loading === "1") {
      return;
    }
    toggle.dataset.loading = "1";
    toggle.disabled = true;
    
    try {
      // Chama API (POST ou DELETE)
      await updateFavorite(produto.id, toggle.checked);
      produto.favorito = toggle.checked;
      
      // Feedback visual
      exibirToast(
        toggle.checked
          ? "Produto adicionado aos favoritos."
          : "Produto removido dos favoritos."
      );
    } catch (error) {
      console.error("Favoritos", error);
      
      // Reverte toggle em caso de erro
      setToggle(!toggle.checked);
      const message = error?.message || "Não foi possível atualizar favoritos.";
      exibirToast(message);
      
      // Sessão expirou
      if (error?.status === 401) {
        showLoginPopup();
      }
    } finally {
      // Reabilita toggle
      toggle.disabled = false;
      delete toggle.dataset.loading;
    }
  });
};

// ===== FUNÇÃO: EXIBIR TOAST (NOTIFICAÇÃO) =====
/**
 * Exibe notificação temporária no canto inferior direito.
 * 
 * Estilo:
 * - Fundo dourado (#d4af37) - cor tema IMPERIUM
 * - Texto preto para contraste
 * - Bordas arredondadas (10px)
 * - Sombra pronunciada
 * - z-index alto (99999) para sobrepor tudo
 * 
 * Animação:
 * Aparece imediatamente e desaparece após 2 segundos.
 * Sem animação de fade (pode ser adicionada com CSS).
 * 
 * Uso:
 * exibirToast("Produto adicionado ao carrinho!");
 * exibirToast("Erro ao processar solicitação");
 * 
 * @param {string} mensagem - Texto da notificação
 */
const exibirToast = (mensagem) => {
  const aviso = document.createElement("div");
  aviso.textContent = mensagem;
  aviso.style.position = "fixed";
  aviso.style.bottom = "20px";
  aviso.style.right = "20px";
  aviso.style.background = "#d4af37";
  aviso.style.color = "#000";
  aviso.style.padding = "12px 18px";
  aviso.style.borderRadius = "10px";
  aviso.style.fontWeight = "600";
  aviso.style.zIndex = "99999";
  aviso.style.boxShadow = "0 10px 20px rgba(0,0,0,0.25)";
  document.body.appendChild(aviso);
  setTimeout(() => aviso.remove(), 2000);
};

let viewerErrorNotified = false;
const notifyViewerIssue = () => {
  if (viewerErrorNotified) {
    return;
  }
  viewerErrorNotified = true;
  exibirToast("Visualização 3D indisponível no momento.");
};

const initCarrinho = (produto) => {
  if (!produto) {
    return;
  }
  const botao = document.getElementById("btn-add-cart");
  if (!botao) {
    return;
  }

  botao.addEventListener("click", () => {
    const tamanhoSelecionado = document.querySelector(
      ".tamanhos button.selected"
    );
    if (!tamanhoSelecionado) {
      alert("Por favor, selecione um tamanho.");
      return;
    }

    const tamanho = tamanhoSelecionado.textContent.trim();
    const carrinho = readStorage(CARRINHO_KEY);
    const itemExistente = carrinho.find(
      (item) => item.id === produto.id && item.tamanho === tamanho
    );

    if (itemExistente) {
      itemExistente.qtd += 1;
    } else {
      carrinho.push({
        id: produto.id,
        nome: produto.nome,
        imagem: produto.imagem,
        preco: produto.preco,
        tamanho,
        qtd: 1,
      });
    }
    writeStorage(CARRINHO_KEY, carrinho);
    exibirToast("Produto adicionado ao carrinho!");
  });
};

// ===== FUNÇÃO: VISUALIZADOR 3D DUPLO (CONJUNTOS) =====
/**
 * Inicializa visualizador 3D para conjuntos (superior + inferior).
 * 
 * Propósito:
 * Renderiza dois modelos simultaneamente (ex: blusa + calça).
 * Modelos são agrupados e renderizados juntos.
 * 
 * Parâmetros:
 * @param {string} modelPathSuperior - URL do modelo da parte superior (.glb)
 * @param {string} modelPathInferior - URL do modelo da parte inferior (.glb)
 * @param {Object} options - Opções de configuração
 * @param {boolean} options.disableImageBitmap - Desabilita ImageBitmap (fallback)
 * 
 * Fluxo:
 * 1. Valida container e estado do viewer
 * 2. Configura proteção ImageBitmap se necessário
 * 3. Carrega ambos os modelos em paralelo
 * 4. Agrupa modelos em THREE.Group
 * 5. Centraliza e escala conjunto
 * 6. Configura câmera, luzes e controles
 * 7. Inicia loop de renderização
 * 
 * Tratamento de erros:
 * - Exibe placeholder se modelos falharem
 * - Tenta novamente sem ImageBitmap se erro específico
 * - Desabilita viewer temporariamente se out of memory
 */
const initThreeViewerDuplo = async (modelPathSuperior, modelPathInferior, options = {}) => {
  console.log('*** initThreeViewerDuplo CHAMADO ***');
  console.log('ModelPath Superior recebido:', modelPathSuperior);
  console.log('ModelPath Inferior recebido:', modelPathInferior);
  
  // ===== VALIDAÇÃO DO CONTAINER =====
  const container3D = document.getElementById("container3D");
  if (!container3D) {
    console.error('Container 3D não encontrado!');
    return;
  }

  // ===== VERIFICAÇÃO DE ESTABILIDADE =====
  /**
   * viewerStabilityGuard previne crashes repetidos.
   * Após N falhas em X minutos, desabilita viewer temporariamente.
   */
  if (viewerStabilityGuard.isDisabled()) {
    console.warn('Viewer desabilitado por instabilidade');
    container3D.classList.add("placeholder");
    notifyViewerIssue();
    return;
  }

  // ===== CONFIGURAÇÃO DE IMAGEBITMAP =====
  /**
   * Se disableImageBitmap=true, desabilita window.createImageBitmap.
   * Força Three.js a usar HTMLImageElement como fallback.
   * Resolve erros "could not be allocated" em GPUs limitadas.
   */
  const { disableImageBitmap = false } = options;
  let releaseImageBitmap = null;
  if (disableImageBitmap) {
    releaseImageBitmap = imageBitmapToggle.disable();
  }
  const releaseImageBitmapOnce = () => {
    if (releaseImageBitmap) {
      releaseImageBitmap();
      releaseImageBitmap = null;
    }
  };
  
  const showPlaceholder = () => {
    container3D.classList.add("placeholder");
  };

  // ===== VALIDAÇÃO DOS MODELOS =====
  if (!modelPathSuperior && !modelPathInferior) {
    console.warn('Nenhum modelo fornecido!');
    showPlaceholder();
    releaseImageBitmapOnce();
    return;
  }

  container3D.classList.remove("placeholder");
  container3D.innerHTML = "";

  try {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x333333);

    const camera = new THREE.PerspectiveCamera(45, container3D.clientWidth / container3D.clientHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container3D.clientWidth, container3D.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    const onResize = () => {
      camera.aspect = container3D.clientWidth / container3D.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container3D.clientWidth, container3D.clientHeight);
    };
    window.addEventListener("resize", onResize);

    const onContextLost = (evt) => {
      evt.preventDefault();
      console.warn("Contexto WebGL perdido");
    };
    const onContextRestored = () => {
      console.info("Contexto WebGL restaurado");
    };
    renderer.domElement.addEventListener("webglcontextlost", onContextLost);
    renderer.domElement.addEventListener("webglcontextrestored", onContextRestored);

    const showPlaceholder = () => {
      container3D.classList.add("placeholder");
      container3D.innerHTML = "";
    };

    container3D.appendChild(renderer.domElement);

    const ambientLight = new THREE.AmbientLight(0xffffff, 1.8);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1.5);
    directionalLight.position.set(3, 5, 5);
    scene.add(directionalLight);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.autoRotate = false;
    controls.minPolarAngle = Math.PI / 4;
    controls.maxPolarAngle = Math.PI / 2;

    const loader = new GLTFLoader();
    let animationFrame = 0;

    const disposeMaterial = (material) => {
      if (!material) return;
      if (Array.isArray(material)) {
        material.forEach(disposeMaterial);
        return;
      }
      Object.keys(material).forEach((key) => {
        const value = material[key];
        if (value && typeof value.dispose === "function") {
          value.dispose();
        }
      });
      material.dispose?.();
    };

    const disposeSceneObjects = () => {
      if (!currentModel) return;
      currentModel.traverse((child) => {
        if (child.isMesh) {
          child.geometry?.dispose?.();
          disposeMaterial(child.material);
        }
      });
      scene.remove(currentModel);
      currentModel = null;
    };

    const cleanup = () => {
      window.removeEventListener("resize", onResize);
      renderer.domElement.removeEventListener("webglcontextlost", onContextLost);
      renderer.domElement.removeEventListener("webglcontextrestored", onContextRestored);
      cancelAnimationFrame(animationFrame);
      disposeSceneObjects();
      renderer.dispose();
      controls.dispose();
    };

    // Grupo para conter ambos os modelos
    const grupoCompleto = new THREE.Group();
    let modelosCarregados = 0;
    const totalModelos = (modelPathSuperior ? 1 : 0) + (modelPathInferior ? 1 : 0);

    const finalizarCarregamento = () => {
      if (modelosCarregados < totalModelos) return;

      // Centralizar e escalar o conjunto completo (modelos maiores e mais próximos)
      const box = new THREE.Box3().setFromObject(grupoCompleto);
      const size = box.getSize(new THREE.Vector3());
      const maxDimension = Math.max(size.x, size.y, size.z) || 1;
      const NORMALIZED_SIZE = 3.5; // Aumentado de 2.5 para 3.5
      const normalizedScale = NORMALIZED_SIZE / maxDimension;
      grupoCompleto.scale.setScalar(normalizedScale);

      box.setFromObject(grupoCompleto);
      const center = box.getCenter(new THREE.Vector3());
      grupoCompleto.position.sub(center);
      
      // Aproximar os modelos um do outro
      grupoCompleto.children.forEach((child, index) => {
        if (index === 1) {
          child.position.z -= 0.3; // Move o segundo modelo mais próximo
        }
      });

      scene.add(grupoCompleto);
      currentModel = grupoCompleto;      const sphere = box.getBoundingSphere(new THREE.Sphere());
      const radius = sphere.radius || 1;
      const fitOffset = 1.2;
      const halfFov = THREE.MathUtils.degToRad(camera.fov / 2);
      const distance = (radius / Math.sin(halfFov)) * fitOffset;
      camera.position.set(0, radius * 0.35, distance);
      controls.target.set(0, 0, 0);
      controls.update();

      releaseImageBitmapOnce();
    };

    // Carregar modelo superior
    if (modelPathSuperior) {
      loader.load(
        modelPathSuperior,
        (gltf) => {
          grupoCompleto.add(gltf.scene);
          modelosCarregados++;
          console.log('Modelo superior carregado');
          finalizarCarregamento();
        },
        undefined,
        (error) => {
          console.error("Erro ao carregar modelo superior", error);
          modelosCarregados++;
          finalizarCarregamento();
        }
      );
    }

    // Carregar modelo inferior
    if (modelPathInferior) {
      loader.load(
        modelPathInferior,
        (gltf) => {
          grupoCompleto.add(gltf.scene);
          modelosCarregados++;
          console.log('Modelo inferior carregado');
          finalizarCarregamento();
        },
        undefined,
        (error) => {
          console.error("Erro ao carregar modelo inferior", error);
          modelosCarregados++;
          finalizarCarregamento();
        }
      );
    }

    const animate = () => {
      animationFrame = requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    };
    animate();
  } catch (error) {
    console.error("Erro ao iniciar viewer 3D duplo", error);
    if (isWebGLOutOfMemory(error) || isWebGLContextInitFailure(error)) {
      markViewerUnstable();
    }
    showPlaceholder();
    releaseImageBitmapOnce();
  }
};

const initThreeViewer = async (modelPath, options = {}) => {
  const container3D = document.getElementById("container3D");
  if (!container3D) {
    return;
  }

  if (viewerStabilityGuard.isDisabled()) {
    container3D.classList.add("placeholder");
    notifyViewerIssue();
    return;
  }

  const { disableImageBitmap = false } = options;
  let releaseImageBitmap = null;
  if (disableImageBitmap) {
    releaseImageBitmap = imageBitmapToggle.disable();
  }
  const releaseImageBitmapOnce = () => {
    if (releaseImageBitmap) {
      releaseImageBitmap();
      releaseImageBitmap = null;
    }
  };
  const markViewerUnstable = () => {
    viewerStabilityGuard.triggerCooldown();
    notifyViewerIssue();
  };

  const showPlaceholder = () => {
    container3D.classList.add("placeholder");
  };

  if (typeof container3D.__threeCleanup === "function") {
    container3D.__threeCleanup();
    container3D.__threeCleanup = null;
  }

  if (!modelPath) {
    showPlaceholder();
    releaseImageBitmapOnce();
    return;
  }

  while (container3D.firstChild) {
    container3D.removeChild(container3D.firstChild);
  }
  container3D.classList.remove("placeholder");

  const MAX_SIZE = 1000;
  const MIN_SIZE = 720;
  const getSize = () => {
    const rawWidth = container3D.clientWidth || container3D.offsetWidth || 0;
    const rawHeight = container3D.clientHeight || container3D.offsetHeight || 0;
    const width = Math.min(Math.max(rawWidth, MIN_SIZE), MAX_SIZE);
    const height = Math.min(Math.max(rawHeight, MIN_SIZE), MAX_SIZE);
    return { width, height };
  };

  try {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color("#333");

    const { width, height } = getSize();
    const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.set(0, 1.5, 5);

    let renderer;
    try {
      renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: false,
        powerPreference: "low-power",
        preserveDrawingBuffer: false,
        stencil: false,
        precision: "mediump",
      });
    } catch (error) {
      console.error("Renderer indisponível", error);
      if (isWebGLContextInitFailure(error) || isWebGLOutOfMemory(error)) {
        markViewerUnstable();
      }
      showPlaceholder();
      releaseImageBitmapOnce();
      return;
    }
    renderer.setPixelRatio(1);
    renderer.setSize(width, height);
    container3D.appendChild(renderer.domElement);

    const ambientLight = new THREE.AmbientLight(0xffffff, 1.8);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1.5);
    directionalLight.position.set(3, 5, 5);
    scene.add(directionalLight);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.autoRotate = false;
    controls.minPolarAngle = Math.PI / 4;
    controls.maxPolarAngle = Math.PI / 2;

    const loader = new GLTFLoader();

    let animationFrame = 0;

    const disposeMaterial = (material) => {
      if (!material) {
        return;
      }
      if (Array.isArray(material)) {
        material.forEach(disposeMaterial);
        return;
      }
      Object.keys(material).forEach((key) => {
        const value = material[key];
        if (value && typeof value.dispose === "function") {
          value.dispose();
        }
      });
      material.dispose?.();
    };

    const disposeSceneObjects = () => {
      if (!currentModel) {
        return;
      }
      currentModel.traverse((child) => {
        if (child.isMesh) {
          child.geometry?.dispose?.();
          disposeMaterial(child.material);
        }
      });
      scene.remove(currentModel);
      currentModel = null;
    };

    const cleanup = () => {
      window.removeEventListener("resize", onResize);
      renderer.domElement.removeEventListener(
        "webglcontextlost",
        onContextLost
      );
      renderer.domElement.removeEventListener(
        "webglcontextrestored",
        onContextRestored
      );
      cancelAnimationFrame(animationFrame);
      disposeSceneObjects();
      controls.dispose();
      renderer.forceContextLoss?.();
      renderer.dispose();
      container3D.__threeCleanup = null;
      releaseImageBitmapOnce();
    };

    const onContextLost = (event) => {
      event.preventDefault();
      markViewerUnstable();
      cleanup();
      showPlaceholder();
    };

    const onContextRestored = () => {
      markViewerUnstable();
      cleanup();
      showPlaceholder();
    };

    const onResize = () => {
      const size = getSize();
      camera.aspect = size.width / size.height;
      camera.updateProjectionMatrix();
      renderer.setSize(size.width, size.height);
    };

    renderer.domElement.addEventListener("webglcontextlost", onContextLost, {
      once: true,
    });
    renderer.domElement.addEventListener(
      "webglcontextrestored",
      onContextRestored,
      { once: true }
    );
    window.addEventListener("resize", onResize);

    container3D.__threeCleanup = cleanup;

    loader.load(
      modelPath,
      (gltf) => {
        const model = gltf.scene;

        // Normalize the model so every asset occupies roughly the same viewport area.
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const maxDimension = Math.max(size.x, size.y, size.z) || 1;
        const NORMALIZED_SIZE = 2.5;
        const normalizedScale = NORMALIZED_SIZE / maxDimension;
        model.scale.setScalar(normalizedScale);

        // Recalculate center after scaling and move model so origin is near its center.
        box.setFromObject(model);
        const center = box.getCenter(new THREE.Vector3());
        model.position.sub(center);
        scene.add(model);
        currentModel = model;

        // Frame the model by moving camera based on bounding sphere radius.
        const sphere = box.getBoundingSphere(new THREE.Sphere());
        const radius = sphere.radius || 1;
        const fitOffset = 1.2;
        const halfFov = THREE.MathUtils.degToRad(camera.fov / 2);
        const distance = (radius / Math.sin(halfFov)) * fitOffset;
        camera.position.set(0, radius * 0.35, distance);
        controls.target.set(0, 0, 0);
        controls.update();

        releaseImageBitmapOnce();
      },
      undefined,
      (error) => {
        releaseImageBitmapOnce();
        if (isWebGLOutOfMemory(error)) {
          markViewerUnstable();
          cleanup();
          showPlaceholder();
          return;
        }
        if (!disableImageBitmap && shouldRetryWithoutImageBitmap(error)) {
          console.warn("Recarregando modelo 3D sem ImageBitmap.");
          cleanup();
          initThreeViewer(modelPath, { disableImageBitmap: true });
          return;
        }
        console.error("Falha ao carregar modelo 3D", error);
        cleanup();
        showPlaceholder();
      }
    );

    const animate = () => {
      animationFrame = requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    };
    animate();
  } catch (error) {
    console.error("Erro ao iniciar viewer 3D", error);
    if (isWebGLOutOfMemory(error) || isWebGLContextInitFailure(error)) {
      markViewerUnstable();
    }
    showPlaceholder();
    releaseImageBitmapOnce();
  }
};

const initAlternanciaConjunto = (produto) => {
  // Verifica se é um conjunto (categoria 5=Masculino ou 11=Feminino)
  if (!produto || (produto.categoriaId !== 5 && produto.categoriaId !== 11)) {
    return;
  }

  const botoesPartes = document.querySelectorAll('.btn-parte');
  if (!botoesPartes.length) {
    return;
  }

  let parteAtual = 'completo';
  const modelPathOriginal = produto.modelPath;

  // Função para carregar modelo específico de cada parte
  const carregarModelo = async (parte) => {
    const pathSuperior = modelPathOriginal; // conjunto_X (com underscore)
    const pathInferior = modelPathOriginal.replace(/conjunto_(\d+)\//, 'conjunto$1/'); // conjuntoX (sem underscore)
    
    console.log(`Carregando modelo da parte: ${parte}`);
    console.log(`Path superior: ${pathSuperior}`);
    console.log(`Path inferior: ${pathInferior}`);
    
    if (parte === 'superior') {
      // Carrega apenas a parte superior
      initThreeViewer(pathSuperior);
    } else if (parte === 'inferior') {
      // Carrega apenas a parte inferior (calça)
      initThreeViewer(pathInferior);
    } else if (parte === 'completo') {
      // Para o completo, carrega ambos os modelos na mesma cena
      initThreeViewerDuplo(pathSuperior, pathInferior);
    }
  };

  botoesPartes.forEach((botao) => {
    botao.addEventListener('click', () => {
      const parte = botao.dataset.parte;
      if (parte === parteAtual) {
        return;
      }

      parteAtual = parte;

      // Atualiza estilos dos botões
      botoesPartes.forEach((btn) => {
        if (btn.dataset.parte === parte) {
          btn.style.background = '#2d3436';
          btn.style.color = 'white';
        } else {
          btn.style.background = '#dfe6e9';
          btn.style.color = '#2d3436';
        }
      });

      // Carrega o modelo da parte selecionada
      carregarModelo(parte);
      
      // Mostra feedback visual
      const textos = {
        'completo': 'Conjunto Completo',
        'superior': 'Parte Superior',
        'inferior': 'Calça'
      };
      exibirToast(`Visualizando: ${textos[parte]}`);
    });
  });
};

// ===== FUNÇÃO: INICIALIZAR PÁGINA DE PRODUTO =====
/**
 * Orquestra inicialização de todos os componentes da página.
 * 
 * Fluxo de inicialização:
 * 1. Parseia dados do produto do HTML (data-produto)
 * 2. Inicializa barra de busca (animação)
 * 3. Renderiza botões de tamanho (PP-XGG ou 38-44)
 * 4. Configura sistema de favoritos (toggle + API)
 * 5. Configura botão adicionar ao carrinho
 * 6. Configura alternância de visualização (conjuntos)
 * 7. Inicializa visualizador 3D apropriado:
 *    - Conjuntos (cat 5/11): initThreeViewerDuplo (2 modelos)
 *    - Outros produtos: initThreeViewer (1 modelo)
 * 
 * Categorias:
 * - 1: Calçados (tamanhos numéricos)
 * - 2-4: Roupas individuais (tamanhos alfabéticos)
 * - 5: Conjuntos masculinos (superior + inferior)
 * - 11: Conjuntos femininos (superior + inferior)
 * 
 * Estrutura de paths para conjuntos:
 * - Superior: /storage/models/conjunto_1/modelo.glb (com underscore)
 * - Inferior: /storage/models/conjunto1/modelo.glb (sem underscore)
 * 
 * Nota:
 * Função roda após DOMContentLoaded para garantir que HTML está pronto.
 */
const initProdutoPage = () => {
  const produto = parseProdutoData();
  console.log('Produto carregado:', produto);
  
  // ===== INICIALIZAÇÃO DOS COMPONENTES =====
  initSearchBar();
  initTamanhos(produto);
  initFavorito(produto);
  initCarrinho(produto);
  initAlternanciaConjunto(produto);
  
  // ===== INICIALIZAÇÃO DO VISUALIZADOR 3D =====
  /**
   * Decisão baseada em categoria:
   * - Conjuntos (5 ou 11): carrega 2 modelos simultaneamente
   * - Outros: carrega modelo único
   */
  if (produto && (produto.categoriaId === 5 || produto.categoriaId === 11) && produto.modelPath) {
    // Conjuntos: superior (conjunto_X) + inferior (conjuntoX)
    const pathSuperior = produto.modelPath; // Ex: /storage/models/conjunto_1/modelo.glb
    const pathInferior = produto.modelPath.replace(/conjunto_(\d+)\//, 'conjunto$1/'); // Ex: /storage/models/conjunto1/modelo.glb
    
    console.log('=== CARREGANDO CONJUNTO ===');
    console.log('Categoria ID:', produto.categoriaId);
    console.log('Path original:', produto.modelPath);
    console.log('Path Superior:', pathSuperior);
    console.log('Path Inferior:', pathInferior);
    
    initThreeViewerDuplo(pathSuperior, pathInferior);
  } else {
    // Produtos normais: modelo único
    console.log('=== CARREGANDO PRODUTO NORMAL ===');
    console.log('Model path:', produto?.modelPath);
    initThreeViewer(produto?.modelPath);
  }
};

// ===== AUTO-INICIALIZAÇÃO =====
/**
 * Aguarda carregamento completo do DOM.
 * Garante que todos os elementos HTML existam antes de acessá-los.
 */
document.addEventListener("DOMContentLoaded", initProdutoPage);
