/**
 * ============================================================
 * MÓDULO: Sistema de Popups/Modais
 * ============================================================
 * 
 * Propósito:
 * Implementa sistema simples de popups para feedback ao usuário.
 * Usado para exibir mensagens de sucesso, erro ou aviso.
 * 
 * Funcionalidades:
 * - Exibir popup com mensagem customizada
 * - Cores dinâmicas (verde para sucesso, vermelho para erro)
 * - Fechar via botão X
 * - Fechar clicando fora (overlay)
 * - API global (showPopup, hidePopup)
 * 
 * Estrutura HTML esperada:
 * <div id="popup-overlay" class="hidden">
 *   <div class="popup-content">
 *     <p id="popup-message"></p>
 *     <button id="popup-close">&times;</button>
 *   </div>
 * </div>
 * 
 * Uso:
 * showPopup("Operação realizada!", "green");
 * showPopup("Erro ao processar.", "red");
 */

// ===== SELEÇÃO DOS ELEMENTOS DO DOM =====

/**
 * Overlay (fundo escurecido) que cobre toda a tela.
 * Classe "hidden" controla visibilidade (display: none).
 */
const popupOverlay = document.getElementById("popup-overlay");

/**
 * Elemento que contém o texto da mensagem.
 * Conteúdo e cor são alterados dinamicamente.
 */
const popupMessage = document.getElementById("popup-message");

/**
 * Botão de fechar (geralmente um "X" no canto).
 * Clique fecha o popup.
 */
const popupClose = document.getElementById("popup-close");

// ===== FUNÇÃO: EXIBIR POPUP =====

/**
 * Exibe popup com mensagem e cor especificadas.
 * 
 * Função global (window.showPopup) para uso em qualquer script.
 * 
 * @param {string} message - Texto a exibir no popup
 * @param {string} color - Cor do texto (CSS: "green", "red", "#ff0000", etc)
 * 
 * Exemplo:
 * showPopup("Produto adicionado ao carrinho!", "green");
 * showPopup("CPF inválido.", "red");
 */
function showPopup(message, color) {
  // Define texto da mensagem
  popupMessage.textContent = message;
  // Define cor do texto (inline style)
  popupMessage.style.color = color;
  // Remove classe "hidden" para exibir popup
  popupOverlay.classList.remove("hidden");
}

// ===== FUNÇÃO: ESCONDER POPUP =====

/**
 * Esconde popup adicionando classe "hidden".
 * 
 * Função global para uso em qualquer script.
 * Pode ser chamada manualmente ou via event listeners.
 */
function hidePopup() {
  popupOverlay.classList.add("hidden");
}

// ===== EVENT LISTENER: BOTÃO FECHAR =====

/**
 * Clique no botão X fecha o popup.
 * Simples chamada para hidePopup().
 */
popupClose.addEventListener("click", hidePopup);

// ===== EVENT LISTENER: CLIQUE NO OVERLAY =====

/**
 * Clique fora do conteúdo do popup (no overlay) também fecha.
 * 
 * Lógica:
 * - Verifica se alvo do clique é o próprio overlay
 * - Se clicar no conteúdo interno, não fecha (event bubbling)
 * - Apenas clique direto no overlay (fundo escuro) fecha
 */
popupOverlay.addEventListener("click", (e) => {
  if (e.target === popupOverlay) {
    hidePopup();
  }
});