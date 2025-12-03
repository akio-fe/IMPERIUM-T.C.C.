/**
 * ============================================================
 * MÓDULO: Animação de Toggle Login/Cadastro
 * ============================================================
 * 
 * Propósito:
 * Alterna entre formulários de login e cadastro com animação CSS.
 * Usado na página de autenticação unificada.
 * 
 * Funcionalidades:
 * - Botão "Cadastre-se": mostra formulário de cadastro
 * - Botão "Já tenho conta": mostra formulário de login
 * - Animação suave de transição (CSS transitions)
 * 
 * Estrutura HTML esperada:
 * <div class="container">
 *   <div class="form-login">...</div>
 *   <div class="form-cadastro">...</div>
 * </div>
 * <button class="register-btn">Cadastre-se</button>
 * <button class="login-btn">Já tenho conta</button>
 * 
 * CSS:
 * - .container.active: exibe formulário de cadastro
 * - .container (sem .active): exibe formulário de login
 */

// ===== SELEÇÃO DOS ELEMENTOS DO DOM =====

/**
 * Container principal que contém ambos os formulários.
 * Classe "active" controla qual formulário é exibido.
 */
const container = document.querySelector('.container');

/**
 * Botão para alternar para cadastro.
 * Geralmente com texto "Cadastre-se" ou "Criar conta".
 */
const registerBtn = document.querySelector('.register-btn');

/**
 * Botão para alternar para login.
 * Geralmente com texto "Já tenho conta" ou "Fazer login".
 */
const loginBtn = document.querySelector('.login-btn');

// ===== EVENT LISTENER: EXIBIR CADASTRO =====

/**
 * Clique no botão de cadastro: adiciona classe "active".
 * 
 * Efeito:
 * - Formulário de login desliza para fora
 * - Formulário de cadastro desliza para dentro
 * - Animação controlada por CSS (transform, opacity, etc)
 */
registerBtn.addEventListener('click', () => {
    container.classList.add('active');
});

// ===== EVENT LISTENER: EXIBIR LOGIN =====

/**
 * Clique no botão de login: remove classe "active".
 * 
 * Efeito:
 * - Formulário de cadastro desliza para fora
 * - Formulário de login desliza para dentro
 * - Retorna ao estado inicial (padrão: login visível)
 */
loginBtn.addEventListener('click', () => {
    container.classList.remove('active');
});