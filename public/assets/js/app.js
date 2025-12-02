/**
 * ============================================================
 * MÓDULO: Slider de Imagens Interativo
 * ============================================================
 * 
 * Propósito:
 * Sistema de carrossel/slider para exibir produtos ou banners promocionais.
 * Permite navegação por setas (próximo/anterior) com animações suaves.
 * 
 * Funcionalidades:
 * - Navegação por botões (Next/Previous)
 * - Rotação automática de slides principais
 * - Miniaturas sincronizadas (thumbnails)
 * - Animações CSS com classes dinâmicas
 * - Estrutura circular (último volta ao primeiro)
 * 
 * Arquitetura:
 * - DOM manipulation (vanilla JavaScript)
 * - Event-driven (onclick handlers)
 * - CSS transitions (classes .next e .prev)
 * - Sincronização entre slider principal e thumbnails
 * 
 * HTML esperado:
 * <div class="slider">
 *   <div class="list">
 *     <div class="item"><!-- Slide principal --></div>
 *   </div>
 *   <div class="thumbnail">
 *     <div class="item"><!-- Miniatura --></div>
 *   </div>
 * </div>
 * <button class="next">→</button>
 * <button class="prev">←</button>
 */

// ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
/**
 * Busca elementos do slider no DOM.
 * 
 * Elementos:
 * - nextBtn: botão para avançar slide (→)
 * - prevBtn: botão para voltar slide (←)
 * - slider: container principal do slider
 * - sliderList: lista de slides principais (visíveis)
 * - thumbnail: container de miniaturas (navegação visual)
 * - thumbnailItems: array de miniaturas individuais
 */
let nextBtn = document.querySelector(".next");
let prevBtn = document.querySelector(".prev");

let slider = document.querySelector(".slider");
let sliderList = slider.querySelector(".slider .list");
let thumbnail = document.querySelector(".slider .thumbnail");
let thumbnailItems = thumbnail.querySelectorAll(".item");

// ===== INICIALIZAÇÃO DO SLIDER =====
/**
 * Move a primeira miniatura para o final da lista.
 * Cria efeito de rotação infinita desde o início.
 * 
 * Comportamento:
 * - Thumbnails iniciam em ordem: [1, 2, 3, 4, 5]
 * - Após este comando: [2, 3, 4, 5, 1]
 * - Facilita sincronização com slides principais
 */
thumbnail.appendChild(thumbnailItems[0]);

// ===== EVENTOS DE CLIQUE DOS BOTÕES =====
/**
 * Configura handlers para botões de navegação.
 * 
 * nextBtn: avança para próximo slide (direita)
 * prevBtn: volta para slide anterior (esquerda)
 * 
 * Ambos chamam moveSlider() com direção correspondente.
 */
// Função para botão "Próximo"
nextBtn.onclick = function () {
  moveSlider("next");
};

// Função para botão "Anterior"
prevBtn.onclick = function () {
  moveSlider("prev");
};

// ===== FUNÇÃO PRINCIPAL: MOVIMENTAÇÃO DO SLIDER =====
/**
 * Controla a transição entre slides com animações.
 * 
 * Fluxo de Execução:
 * 1. Seleciona todos os slides e thumbnails atuais
 * 2. Move elementos no DOM (appendChild ou prepend)
 * 3. Adiciona classe CSS para animação (.next ou .prev)
 * 4. Aguarda fim da animação (animationend event)
 * 5. Remove classe CSS para preparar próxima transição
 * 
 * Técnica de Rotação:
 * - Next: move primeiro item para o final (appendChild)
 * - Prev: move último item para o início (prepend)
 * - DOM reordenação + CSS transitions = animação suave
 * 
 * Sincronização:
 * - Slider principal e thumbnails movem juntos
 * - Mantém correspondência visual entre eles
 * 
 * @param {string} direction - "next" para avançar, "prev" para voltar
 */
function moveSlider(direction) {
  // Obtém referências atualizadas dos itens (podem ter mudado de ordem)
  let sliderItems = sliderList.querySelectorAll(".item");
  let thumbnailItems = document.querySelectorAll(".thumbnail .item");

  if (direction === "next") {
    // ===== MOVIMENTO PARA FRENTE =====
    // Move primeiro slide para o final da lista
    sliderList.appendChild(sliderItems[0]);
    // Sincroniza: move primeira thumbnail para o final
    thumbnail.appendChild(thumbnailItems[0]);
    // Adiciona classe CSS para animação de entrada pela direita
    slider.classList.add("next");
  } else {
    // ===== MOVIMENTO PARA TRÁS =====
    // Move último slide para o início da lista
    sliderList.prepend(sliderItems[sliderItems.length - 1]);
    // Sincroniza: move última thumbnail para o início
    thumbnail.prepend(thumbnailItems[thumbnailItems.length - 1]);
    // Adiciona classe CSS para animação de entrada pela esquerda
    slider.classList.add("prev");
  }

  // ===== LIMPEZA PÓS-ANIMAÇÃO =====
  /**
   * Remove classe CSS após animação completar.
   * 
   * Evento: animationend
   * - Dispara quando animação CSS termina
   * - Permite reutilizar classes .next/.prev
   * 
   * Opção { once: true }:
   * - Remove listener automaticamente após primeira execução
   * - Previne memory leaks (vazamento de memória)
   * - Evita múltiplas execuções acidentais
   */
  slider.addEventListener(
    "animationend",
    function () {
      if (direction === "next") {
        slider.classList.remove("next");
      } else {
        slider.classList.remove("prev");
      }
    },
    { once: true }
  ); // Remove o event listener após ser acionado uma vez
}
