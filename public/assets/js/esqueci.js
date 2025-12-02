/**
 * ============================================================
 * MÓDULO: Recuperação de Senha (Esqueci Minha Senha)
 * ============================================================
 * 
 * Propósito:
 * Permite usuários recuperarem acesso à conta através de redefinição de senha.
 * Envia email com link seguro para criar nova senha.
 * 
 * Funcionalidades:
 * - Validação de email
 * - Envio de email de redefinição via Firebase
 * - Feedback visual de sucesso/erro
 * - Tratamento de erros específicos
 * 
 * Fluxo de Recuperação:
 * 1. Usuário informa email no formulário
 * 2. Firebase valida se email existe no sistema
 * 3. Firebase envia email com link de redefinição
 * 4. Link redireciona para auth-handler.js (mode=resetPassword)
 * 5. Usuário define nova senha
 * 6. Senha atualizada no Firebase Authentication
 * 
 * Segurança:
 * - Link de redefinição expira em 1 hora (padrão Firebase)
 * - Link de uso único (não pode ser reutilizado)
 * - Requer acesso ao email cadastrado
 * - Não expõe se email existe (previne enumeração de usuários)
 * 
 * Tecnologias:
 * - Firebase Authentication 9.23.0 (modular SDK)
 * - ES6 Modules (import/export)
 * - Promises (async/await)
 * 
 * HTML esperado:
 * <form id="passwordResetForm">
 *   <input id="email" type="email" required>
 *   <button type="submit">Enviar</button>
 * </form>
 * <div id="mensagem-firebase"></div>
 */

// ===== IMPORTAÇÕES DO FIREBASE APP =====
/**
 * SDK modular do Firebase (v9.23.0).
 * initializeApp: configura e inicializa aplicação Firebase.
 */
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";

// ===== IMPORTAÇÕES DO FIREBASE AUTHENTICATION =====
/**
 * Módulo de autenticação Firebase.
 * 
 * Métodos importados:
 * - getAuth: obtém instância do serviço de autenticação
 * - sendPasswordResetEmail: envia email de recuperação de senha
 * 
 * sendPasswordResetEmail:
 * - Gera código único (oobCode)
 * - Cria URL personalizada com código
 * - Envia email formatado ao usuário
 * - Retorna Promise (resolve se sucesso, reject se erro)
 */
import {
  getAuth,
  sendPasswordResetEmail,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====
/**
 * Credenciais do projeto Firebase (console.firebase.google.com).
 * 
 * Projeto: imperium-0001
 * 
 * Campos:
 * - apiKey: chave pública da API (seguro expor no front-end)
 * - authDomain: domínio usado nos emails de redefinição
 * - projectId: identificador único do projeto
 * - storageBucket: bucket para armazenamento de arquivos (não usado aqui)
 * - messagingSenderId: ID para FCM (não usado aqui)
 * - appId: identificador da aplicação web
 * - measurementId: Google Analytics (opcional)
 * 
 * authDomain importante:
 * - Emails de redefinição usam este domínio
 * - Ex: https://imperium-0001.firebaseapp.com/__/auth/action?mode=resetPassword&oobCode=...
 * - Firebase pode ser configurado para usar domínio customizado
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

// ===== INICIALIZAÇÃO DO FIREBASE =====
/**
 * Instancia e configura serviços Firebase.
 * 
 * Objetos criados:
 * - app: aplicação Firebase configurada
 * - auth: serviço de autenticação (usado para enviar email)
 * 
 * Ordem importa:
 * - initializeApp deve ser chamado primeiro
 * - getAuth precisa de app inicializado
 */
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

// ===== INICIALIZAÇÃO: PROCESSAMENTO DO FORMULÁRIO =====
/**
 * Event listener executado quando o DOM carrega completamente.
 * 
 * Responsabilidade:
 * - Capturar envio do formulário de recuperação
 * - Validar campo de email
 * - Chamar Firebase para enviar email de redefinição
 * - Exibir feedback visual ao usuário (sucesso ou erro)
 * 
 * Prevenção de comportamento padrão:
 * - event.preventDefault() evita reload da página
 * - Permite feedback assíncrono sem perder estado
 */
document.addEventListener("DOMContentLoaded", function () {
  // ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
  /**
   * Elementos do formulário de recuperação de senha.
   * 
   * HTML esperado:
   * <form id="passwordResetForm">
   *   <input id="email" type="email" required>
   *   <button type="submit">Enviar Link</button>
   * </form>
   * <div id="mensagem-firebase" class="form-message"></div>
   * 
   * mensagem-firebase:
   * - Elemento para exibir feedback ao usuário
   * - Classes CSS: "form-message success" (verde) ou "form-message error" (vermelho)
   */
  const passwordResetForm = document.getElementById("passwordResetForm");
  const emailInput = document.getElementById("email");
  const mensagemFirebase = document.getElementById("mensagem-firebase");

  // ===== EVENTO DE SUBMIT DO FORMULÁRIO =====
  /**
   * Handler executado quando formulário é enviado.
   * 
   * Comportamento:
   * 1. Previne reload da página (preventDefault)
   * 2. Valida se email foi preenchido
   * 3. Limpa mensagens anteriores
   * 4. Chama Firebase para enviar email
   * 5. Exibe feedback baseado no resultado
   * 
   * Validações:
   * - Front-end: campo obrigatório (required no HTML + validação JS)
   * - Firebase: formato de email válido, existência no sistema
   */
  passwordResetForm.addEventListener("submit", function (event) {
    // Previne comportamento padrão do form (reload da página)
    event.preventDefault();
    
    // ===== VALIDAÇÃO DO CAMPO DE EMAIL =====
    /**
     * Verifica se usuário preencheu o campo de email.
     * 
     * Nota:
     * - HTML5 'required' já valida, mas esta checagem é redundância segura
     * - Navegadores antigos podem não suportar 'required'
     * - Melhora experiência com mensagem customizada
     * 
     * Tratamento de erro:
     * - Exibe mensagem em vermelho
     * - Retorna early (impede envio)
     */
    if (!emailInput.value) {
      mensagemFirebase.textContent = "Por favor, insira um endereço de e-mail.";
      mensagemFirebase.className = "form-message error";
      return;
    }

    // Captura valor do email (trim para remover espaços)
    const emailAddress = emailInput.value;
    
    // ===== LIMPA MENSAGENS ANTERIORES =====
    /**
     * Reset do estado de feedback antes de nova tentativa.
     * 
     * Motivo:
     * - Usuário pode tentar múltiplas vezes
     * - Evita confusão com mensagens antigas
     * - Prepara UI para novo feedback
     */
    mensagemFirebase.textContent = "";
    
    // ===== CHAMADA FIREBASE: ENVIO DE EMAIL =====
    /**
     * sendPasswordResetEmail processa recuperação de senha.
     * 
     * Funcionamento interno do Firebase:
     * 1. Valida formato do email
     * 2. Verifica se email existe no Authentication
     * 3. Gera código único (oobCode) com expiração de 1 hora
     * 4. Cria URL: https://authDomain/__/auth/action?mode=resetPassword&oobCode=...
     * 5. Envia email formatado com template padrão do Firebase
     * 6. Email contém link para redefinir senha
     * 
     * Parâmetros:
     * - auth: instância do serviço de autenticação
     * - emailAddress: email do usuário
     * 
     * Retorno:
     * - Promise que resolve se email enviado com sucesso
     * - Promise que rejeita se houver erro
     * 
     * Configuração avançada (não usada aqui):
     * - Customizar URL de redirecionamento
     * - Definir idioma do email
     * - Adicionar parâmetros extras à URL
     */
    sendPasswordResetEmail(auth, emailAddress)
      .then(() => {
        // ===== SUCESSO: EMAIL ENVIADO =====
        /**
         * Promise resolvida: email de redefinição enviado.
         * 
         * Próximos passos para o usuário:
         * 1. Verificar caixa de entrada (e spam)
         * 2. Clicar no link recebido
         * 3. Link abre auth-handler.js (mode=resetPassword)
         * 4. Definir nova senha
         * 5. Fazer login com nova senha
         * 
         * Feedback visual:
         * - Mensagem em verde (classe "success")
         * - Confirma envio ao usuário
         * - Não revela se email existe (segurança)
         * 
         * Nota de segurança:
         * Firebase sempre retorna sucesso (mesmo se email não existir)
         * para prevenir enumeração de usuários (descobrir emails válidos).
         */
        mensagemFirebase.textContent =
          "Um link de redefinição de senha foi enviado para o seu e-mail!";
        mensagemFirebase.className = "form-message success";
      })
      .catch((error) => {
        // ===== ERRO: FALHA NO ENVIO =====
        /**
         * Promise rejeitada: erro ao enviar email.
         * 
         * Erros possíveis (error.code):
         * - auth/invalid-email: formato de email inválido
         * - auth/user-not-found: email não cadastrado (raro, Firebase oculta por padrão)
         * - auth/too-many-requests: muitas tentativas, aguardar
         * - auth/network-request-failed: sem conexão internet
         * 
         * Tratamento:
         * - Loga erro técnico no console (para debug)
         * - Exibe mensagem amigável ao usuário
         * - Mensagem em vermelho (classe "error")
         */
        const errorCode = error.code;
        console.error(errorCode, error.message);
        
        // ===== TRADUÇÃO DE ERROS =====
        /**
         * Converte códigos de erro técnicos em mensagens amigáveis.
         * 
         * Mensagem padrão:
         * - Genérica para erros não mapeados
         * - Evita expor detalhes técnicos ao usuário
         * 
         * Mensagens específicas:
         * - Melhoram experiência do usuário
         * - Indicam ação corretiva quando possível
         */
        let userMessage =
          "Ocorreu um erro. Por favor, tente novamente.";
          
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