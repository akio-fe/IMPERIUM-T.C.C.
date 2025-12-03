/**
 * ============================================================
 * MÓDULO: Recuperação de Senha
 * ============================================================
 * 
 * Propósito:
 * Implementa funcionalidade "Esqueci minha senha".
 * Envia email de redefinição via Firebase Authentication.
 * 
 * Funcionalidades:
 * - Validação de email
 * - Envio de link de reset via Firebase
 * - Feedback visual de sucesso/erro
 * - Tratamento de erros específicos (email não encontrado, formato inválido)
 * 
 * Fluxo:
 * 1. Usuário digita email no formulário
 * 2. Clica em "Enviar link de recuperação"
 * 3. Firebase envia email com link único
 * 4. Link redireciona para auth-handler.js (resetPassword mode)
 * 5. Usuário define nova senha
 * 
 * Tecnologias:
 * - Firebase Authentication (sendPasswordResetEmail)
 * - ES6 Modules
 */

// ===== IMPORTAÇÕES DO FIREBASE =====

import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import {
  getAuth,
  sendPasswordResetEmail,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====

/**
 * Credenciais do projeto Firebase (IMPERIUM-0001).
 * Mesmas credenciais usadas em cadastro_login.js e delete.js.
 */
const firebaseConfig = {
  apiKey: "AIzaSyBtblDahBpfrT4CaLl2viS0D2890iJ_RFE",
  authDomain: "imperium-0001.firebaseapp.com",
  projectId: "imperium-0001",
  storageBucket: "imperium-0001.firebasestorage.app",
  messagingSenderId: "961834611988",
  appId: "1:961834611988:web:0a2ad6089630324094be01",
  measurementId: "G-M39V86RLKS",
};

// Inicializa aplicação Firebase
const app = initializeApp(firebaseConfig);
// Obtém instância do serviço de autenticação
const auth = getAuth(app);

// ===== INICIALIZAÇÃO APÓS CARREGAMENTO DO DOM =====

/**
 * Aguarda carregamento completo do DOM antes de executar.
 * Garante que elementos HTML estejam disponíveis.
 */
document.addEventListener("DOMContentLoaded", function () {
  // ===== SELEÇÃO DOS ELEMENTOS HTML =====
  
  /**
   * Formulário de recuperação de senha.
   * HTML esperado: <form id="passwordResetForm">...</form>
   */
  const passwordResetForm = document.getElementById("passwordResetForm");
  
  /**
   * Campo de input do email.
   * HTML: <input id="email" type="email" required>
   */
  const emailInput = document.getElementById("email");
  
  /**
   * Elemento para exibir mensagens de feedback.
   * HTML: <div id="mensagem-firebase"></div>
   */
  const mensagemFirebase = document.getElementById("mensagem-firebase");

  // ===== EVENT LISTENER: SUBMIT DO FORMULÁRIO =====
  
  /**
   * Handler do submit do formulário de recuperação.
   * Previne comportamento padrão (reload da página).
   */
  passwordResetForm.addEventListener("submit", function (event) {
    // Previne envio tradicional do formulário (POST/GET)
    event.preventDefault();
    
    // ===== VALIDAÇÃO CLIENT-SIDE =====
    
    /**
     * Verifica se email foi preenchido.
     * Validação adicional à validação HTML5 (required).
     */
    if (!emailInput.value) {
      mensagemFirebase.textContent = "Por favor, insira um endereço de e-mail.";
      mensagemFirebase.className = "form-message error";
      return;
    }

    // Captura valor do email
    const emailAddress = emailInput.value;
    
    // Limpa mensagens anteriores (estado limpo para nova tentativa)
    mensagemFirebase.textContent = "";
    
    // ===== ENVIO DO EMAIL DE RECUPERAÇÃO =====
    
    /**
     * sendPasswordResetEmail: método do Firebase Auth.
     * 
     * Parâmetros:
     * - auth: instância de autenticação
     * - emailAddress: email do usuário
     * 
     * Comportamento:
     * - Verifica se email existe no Firebase Auth
     * - Envia email com link único de recuperação
     * - Link válido por 1 hora (padrão Firebase)
     * - Email customizável no console Firebase
     * 
     * Retorno: Promise (then/catch)
     */
    sendPasswordResetEmail(auth, emailAddress)
      .then(() => {
        // ===== SUCESSO: EMAIL ENVIADO =====
        
        /**
         * Email enviado com sucesso.
         * Usuário deve verificar caixa de entrada (e spam).
         */
        mensagemFirebase.textContent =
          "Um link de redefinição de senha foi enviado para o seu e-mail!";
        mensagemFirebase.className = "form-message success";
      })
      .catch((error) => {
        // ===== ERRO: TRATAMENTO DE EXCEÇÕES =====
        
        /**
         * Captura código de erro do Firebase.
         * Códigos comuns:
         * - auth/user-not-found: email não cadastrado
         * - auth/invalid-email: formato inválido
         * - auth/too-many-requests: rate limit excedido
         */
        const errorCode = error.code;
        console.error(errorCode, error.message);
        
        // Mensagem padrão (fallback)
        let userMessage =
          "Ocorreu um erro. Por favor, tente novamente.";
        
        // ===== MAPEAMENTO DE ERROS PARA MENSAGENS AMIGÁVEIS =====
        
        if (errorCode === "auth/user-not-found") {
          userMessage = "Não há nenhum usuário registrado com este e-mail.";
        } else if (errorCode === "auth/invalid-email") {
          userMessage = "O e-mail fornecido é inválido.";
        }
        
        // Exibe mensagem de erro ao usuário
        mensagemFirebase.textContent = userMessage;
        mensagemFirebase.className = "form-message error";
      });
  });
});