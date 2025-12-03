/**
 * ============================================================
 * MÓDULO: Carrossel/Slider de Imagens
 * ============================================================
 * 
 * Propósito:
 * Implementa carrossel interativo de imagens com navegação por botões.
 * Sincroniza slider principal com miniaturas (thumbnails).
 * 
 * Funcionalidades:
 * - Navegação próximo/anterior
 * - Animações CSS suaves (classes "next" e "prev")
 * - Reordenação do DOM para efeito infinito
 * - Sincronização slider ↔ thumbnails
 * 
 * Estrutura HTML esperada:
 * <div class="slider">
 *   <div class="list">
 *     <div class="item"><!-- Slide --></div>
 *     <div class="item"><!-- Slide --></div>
 *   </div>
 *   <div class="thumbnail">
 *     <div class="item"><!-- Miniatura --></div>
 *     <div class="item"><!-- Miniatura --></div>
 *   </div>
 * </div>
 * <button class="prev">←</button>
 * <button class="next">→</button>
 */

// ===== SELEÇÃO DOS ELEMENTOS DO DOM =====

// Botões de navegação
let nextBtn = document.querySelector(".next");
let prevBtn = document.querySelector(".prev");

// Container principal do slider
let slider = document.querySelector(".slider");
// Lista de slides principais (imagens grandes)
let sliderList = slider.querySelector(".slider .list");
// Container de miniaturas (thumbnails)
let thumbnail = document.querySelector(".slider .thumbnail");
// Array de miniaturas individuais
let thumbnailItems = thumbnail.querySelectorAll(".item");

// ===== INICIALIZAÇÃO =====
/**
 * Move a primeira miniatura para o final.
 * Isso prepara o carrossel para animações circulares.
 */
thumbnail.appendChild(thumbnailItems[0]);

// ===== EVENT LISTENERS: BOTÕES DE NAVEGAÇÃO =====

/**
 * Botão "Próximo": avança para o próximo slide.
 * Move o primeiro item para o final (animação → direita).
 */
nextBtn.onclick = function () {
  moveSlider("next");
};

/**
 * Botão "Anterior": volta para o slide anterior.
 * Move o último item para o início (animação ← esquerda).
 */
prevBtn.onclick = function () {
  moveSlider("prev");
};

// ===== FUNÇÃO PRINCIPAL: MOVIMENTAÇÃO DO SLIDER =====
/**
 * Move o slider na direção especificada.
 * 
 * Lógica:
 * 1. Reordena elementos do DOM (append ou prepend)
 * 2. Adiciona classe CSS para animação ("next" ou "prev")
 * 3. Remove classe após animação terminar (animationend event)
 * 
 * @param {string} direction - "next" ou "prev"
 */
function moveSlider(direction) {
  // Captura slides e thumbnails atuais
  let sliderItems = sliderList.querySelectorAll(".item");
  let thumbnailItems = document.querySelectorAll(".thumbnail .item");

  if (direction === "next") {
    // Próximo: move primeiro item para o final
    sliderList.appendChild(sliderItems[0]);
    thumbnail.appendChild(thumbnailItems[0]);
    // Classe "next" aciona animação CSS (slide para esquerda)
    slider.classList.add("next");
  } else {
    // Anterior: move último item para o início
    sliderList.prepend(sliderItems[sliderItems.length - 1]);
    thumbnail.prepend(thumbnailItems[thumbnailItems.length - 1]);
    // Classe "prev" aciona animação CSS (slide para direita)
    slider.classList.add("prev");
  }

  /**
   * Listener de animação: limpa classe após conclusão.
   * 
   * Opções:
   * - once: true → Remove automaticamente após primeira execução
   * 
   * Isso previne acúmulo de listeners e permite novas animações.
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
