/**
 * ============================================================
 * MÓDULO: Toggle de Cartão de Autenticação
 * ============================================================
 * 
 * Propósito:
 * Alterna entre estados de login e cadastro com animação flip.
 * Usado em páginas de autenticação com cartão 3D (flip card).
 * 
 * Funcionalidades:
 * - Botão "Login": mostra frente do cartão (formulário de login)
 * - Botão "Cadastro": vira cartão (formulário de cadastro)
 * - Animação CSS 3D (rotateY)
 * - Validação de existência de elementos (guard clause)
 * 
 * Estrutura HTML esperada:
 * <div class="card">
 *   <div class="card-front"><!-- Login --></div>
 *   <div class="card-back"><!-- Cadastro --></div>
 * </div>
 * <button class="loginButton">Login</button>
 * <button class="cadastroButton">Cadastro</button>
 * 
 * CSS:
 * - .card.loginActive: frente visível (login)
 * - .card.cadastroActive: verso visível (cadastro)
 * - transform: rotateY(180deg) para flip
 */

// ===== SELEÇÃO DOS ELEMENTOS DO DOM =====

/**
 * Cartão 3D que contém ambos os formulários.
 * Classes "loginActive" e "cadastroActive" controlam rotação.
 */
let card = document.querySelector(".card");

/**
 * Botão para exibir formulário de login (frente do cartão).
 */
let loginButton = document.querySelector(".loginButton");

/**
 * Botão para exibir formulário de cadastro (verso do cartão).
 */
let cadastroButton = document.querySelector(".cadastroButton");

// ===== VALIDAÇÃO: ELEMENTOS EXISTEM =====

/**
 * Guard clause: verifica se todos os elementos foram encontrados.
 * Se algum estiver ausente (null), não inicializa funcionalidade.
 * 
 * Isso previne erros em páginas que não usam este cartão.
 */
if (card && loginButton && cadastroButton) {
    // ===== EVENT LISTENER: EXIBIR LOGIN =====
    
    /**
     * Clique no botão de login:
     * - Remove estado de cadastro (se existir)
     * - Adiciona estado de login
     * - Cartão vira para frente (rotateY(0deg))
     */
    loginButton.onclick = () => {
        card.classList.remove("cadastroActive");
        card.classList.add("loginActive");
    };

    // ===== EVENT LISTENER: EXIBIR CADASTRO =====
    
    /**
     * Clique no botão de cadastro:
     * - Remove estado de login (se existir)
     * - Adiciona estado de cadastro
     * - Cartão vira para trás (rotateY(180deg))
     */
    cadastroButton.onclick = () => {
        card.classList.remove("loginActive");
        card.classList.add("cadastroActive");
    };
} else {
    /**
     * Aviso no console se elementos não foram encontrados.
     * Útil para debugging em páginas que incluem este script
     * mas não possuem a estrutura necessária.
     */
    console.warn("Login/Cadastro toggle not initialized. Missing card or button elements.");
}