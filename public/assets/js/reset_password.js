/**
 * ============================================================
 * MÓDULO: Recuperação de Senha
 * ============================================================
 * 
 * Arquivo: public/assets/js/reset_password.js
 * Propósito: Gerencia o envio de email de redefinição de senha via Firebase.
 * 
 * Funcionalidades:
 * - Valida email digitado pelo usuário
 * - Envia requisição ao Firebase Authentication
 * - Exibe feedback visual (sucesso/erro) via popups
 * - Gerencia estados do botão (normal/loading)
 * - Trata erros específicos do Firebase
 * 
 * Fluxo Detalhado:
 * 1. Usuário digita email no formulário
 * 2. Clica em "Enviar link"
 * 3. JavaScript valida email (não vazio, formato válido)
 * 4. Botão entra em loading state ("Enviando...")
 * 5. Firebase.sendPasswordResetEmail() é chamado
 * 6. Firebase verifica se email existe no sistema
 * 7. Se existe: envia email com link único
 * 8. Se não existe: retorna erro (mas não revela ao usuário)
 * 9. Popup verde de sucesso ou vermelho de erro
 * 10. Botão volta ao estado normal
 * 
 * Método Firebase utilizado:
 * sendPasswordResetEmail(auth, email)
 * - Envia email de redefinição para o endereço fornecido
 * - Email contém link com código único (oobCode)
 * - Link válido por 1 hora (padrão Firebase)
 * - Template do email configurado no Firebase Console
 * 
 * Tratamento de Erros:
 * - auth/missing-email: email não fornecido
 * - auth/invalid-email: formato inválido
 * - auth/user-not-found: email não cadastrado
 * - auth/network-request-failed: sem conexão
 * - auth/too-many-requests: rate limit excedido
 * 
 * Segurança:
 * - Firebase não revela se email existe (prevenção de enumeração)
 * - Rate limiting automático (máximo 5 envios/hora)
 * - Link de redefinição é single-use
 * - Código expira após 1 hora
 * 
 * Dependências:
 * - Firebase App SDK 9.6.1 (modular)
 * - Firebase Auth SDK 9.6.1
 * - firebase-config.js (configuração compartilhada)
 * - popups.js (função global showPopup)
 * 
 * Integração:
 * - esqueci_senha.html: HTML do formulário
 * - Firebase Console: configuração de email template
 * - auth.html: página de destino do link (processamento)
 */

// ===== IMPORTAÇÕES DO FIREBASE SDK =====

/**
 * Import do SDK modular do Firebase v9.6.1.
 * 
 * Módulos importados:
 * - firebase-app: inicialização do app Firebase
 * - firebase-auth: métodos de autenticação
 * 
 * Por que CDN (gstatic.com)?
 * - Carregamento rápido (servidores globais do Google)
 * - Cache compartilhado entre sites
 * - Sem necessidade de build/bundle
 * - Versão específica (9.6.1) garante estabilidade
 */
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.6.1/firebase-app.js";
import { getAuth, sendPasswordResetEmail } from "https://www.gstatic.com/firebasejs/9.6.1/firebase-auth.js";

/**
 * Importa configuração Firebase de módulo compartilhado.
 * 
 * Vantagens da centralização:
 * - Configuração única para todos os módulos de auth
 * - Facilita atualização de credenciais
 * - Evita duplicação de código
 * - Manutenção simplificada
 */
import { firebaseConfig } from "./firebase-config.js";

// ===== INICIALIZAÇÃO DO FIREBASE =====

/**
 * Inicializa aplicação Firebase com credenciais do projeto.
 * 
 * Parâmetro:
 * - firebaseConfig: objeto com apiKey, authDomain, projectId, etc
 * 
 * Retorno:
 * - Instância do app Firebase configurado
 * 
 * Esta instância é usada para obter serviços específicos (auth, firestore, etc)
 */
const app = initializeApp(firebaseConfig);

/**
 * Obtém instância do serviço de autenticação do Firebase.
 * 
 * Parâmetro:
 * - app: instância do Firebase App
 * 
 * Retorno:
 * - Instância do Firebase Auth
 * 
 * Métodos disponíveis:
 * - sendPasswordResetEmail(auth, email)
 * - signInWithEmailAndPassword(auth, email, password)
 * - createUserWithEmailAndPassword(auth, email, password)
 * - etc
 */
const auth = getAuth(app);

// ===== REFERÊNCIAS DOS ELEMENTOS HTML =====

/**
 * Formulário de recuperação de senha.
 * 
 * HTML esperado:
 * <form id="forgotPasswordForm">...</form>
 * 
 * Usado para:
 * - Adicionar event listener de submit
 * - Resetar campos após envio bem-sucedido
 */
const resetForm = document.getElementById("forgotPasswordForm");

/**
 * Campo de input do email.
 * 
 * HTML esperado:
 * <input type="email" id="reset-email" name="email" required>
 * 
 * Usado para:
 * - Obter valor digitado pelo usuário
 * - Validar formato de email
 * - Enviar para Firebase
 */
const emailInput = document.getElementById("reset-email");

/**
 * Botão de envio do formulário.
 * 
 * HTML esperado:
 * <button type="submit" id="reset-submit">Enviar link</button>
 * 
 * Usado para:
 * - Gerenciar estado de loading (disabled durante envio)
 * - Alterar texto ("Enviando..." vs "Enviar link")
 * - Feedback visual ao usuário
 */
const submitButton = document.getElementById("reset-submit");

// ===== FUNÇÃO: GERENCIAR ESTADO DO BOTÃO =====

/**
 * Alterna estado do botão entre normal e loading.
 * 
 * Propósito:
 * - Feedback visual durante requisição assíncrona
 * - Previne múltiplos cliques/envios simultâneos
 * - Melhora experiência do usuário (UX)
 * 
 * @param {boolean} isLoading - True para loading, false para normal
 * 
 * Comportamento:
 * - isLoading = true:
 *   * Botão desabilitado (não clicável)
 *   * Texto muda para "Enviando..."
 *   * Cursor muda para "not-allowed" (CSS)
 * 
 * - isLoading = false:
 *   * Botão habilitado (clicável)
 *   * Texto volta para "Enviar link"
 *   * Cursor volta para "pointer" (CSS)
 * 
 * Guard clause:
 * - Verifica se botão existe antes de manipular
 * - Previne erro se elemento não for encontrado
 * - Útil em ambientes de teste ou páginas parciais
 */
function setLoadingState(isLoading) {
  // Guard clause: retorna cedo se botão não existir
  if (!submitButton) {
    return;
  }
  
  // Desabilita/habilita botão
  submitButton.disabled = isLoading;
  
  // Altera texto conforme estado
  submitButton.textContent = isLoading ? "Enviando..." : "Enviar link";
}

// ===== EVENT LISTENER: SUBMIT DO FORMULÁRIO =====

/**
 * Guard clause: verifica se formulário existe.
 * Previne erro se script for carregado em página sem o formulário.
 */
if (resetForm) {
  /**
   * Handler do evento submit do formulário.
   * Função assíncrona (async) para aguardar resposta do Firebase.
   * 
   * Fluxo:
   * 1. Previne comportamento padrão (reload da página)
   * 2. Coleta e valida email
   * 3. Entra em loading state
   * 4. Chama Firebase API
   * 5. Exibe resultado (sucesso/erro)
   * 6. Sai de loading state
   * 
   * @param {Event} event - Evento de submit do formulário
   */
  resetForm.addEventListener("submit", async (event) => {
    // ===== ETAPA 1: PREVENIR RELOAD DA PÁGINA =====
    /**
     * preventDefault() impede comportamento padrão do formulário.
     * 
     * Sem isso:
     * - Página seria recarregada
     * - JavaScript perderia controle
     * - Não seria possível exibir feedback dinâmico
     */
    event.preventDefault();

    // ===== ETAPA 2: COLETA E VALIDAÇÃO DO EMAIL =====
    /**
     * Obtém valor do input e remove espaços em branco.
     * 
     * trim():
     * - Remove espaços no início e fim
     * - Previne envio de emails com espaços acidentais
     * - Exemplo: " usuario@email.com " vira "usuario@email.com"
     */
    const email = emailInput.value.trim();

    // ===== VALIDAÇÃO CLIENT-SIDE: EMAIL VAZIO =====
    /**
     * Verifica se email foi fornecido.
     * 
     * Guard clause:
     * - Retorna cedo se email estiver vazio
     * - Exibe popup vermelho de erro
     * - Não faz requisição ao Firebase
     * 
     * Nota:
     * HTML5 required já valida isso, mas é boa prática validar no JS também.
     */
    if (!email) {
      showPopup("Por favor, informe um e-mail válido.", "red");
      return;
    }

    // ===== ETAPA 3: ATIVAR LOADING STATE =====
    /**
     * Desabilita botão e muda texto para "Enviando...".
     * 
     * Propósito:
     * - Feedback visual de processamento
     * - Previne múltiplos cliques/envios
     * - Melhora percepção de performance
     */
    setLoadingState(true);

    // ===== ETAPA 4: ENVIO DA REQUISIÇÃO AO FIREBASE =====
    try {
      /**
       * Envia requisição de redefinição de senha ao Firebase.
       * 
       * Método: sendPasswordResetEmail(auth, email)
       * 
       * Parâmetros:
       * - auth: instância do Firebase Auth
       * - email: endereço para enviar link de redefinição
       * 
       * Processo no Firebase:
       * 1. Verifica se email existe no sistema
       * 2. Gera código único (oobCode)
       * 3. Envia email com link contendo o código
       * 4. Link válido por 1 hora
       * 
       * Email enviado contém:
       * - Link para página de redefinição (configurada no Firebase Console)
       * - Parâmetros: ?mode=resetPassword&oobCode=ABC123...
       * - Instruções para o usuário
       * 
       * await:
       * - Aguarda resposta do Firebase antes de continuar
       * - Promise resolvida = email enviado com sucesso
       * - Promise rejeitada = erro capturado no catch
       */
      await sendPasswordResetEmail(auth, email);
      
      // ===== ETAPA 5A: SUCESSO - EXIBIR CONFIRMAÇÃO =====
      /**
       * Exibe popup verde de sucesso.
       * 
       * Mensagem genérica:
       * - Não revela se email existe (segurança)
       * - Sempre mostra mensagem de sucesso
       * - Previne enumeração de emails cadastrados
       * 
       * Nota de segurança:
       * Firebase pode retornar sucesso mesmo se email não existir,
       * dependendo da configuração de segurança no console.
       */
      showPopup(
        "Enviamos um link de redefinição de senha para o seu e-mail.",
        "green"
      );
      
      /**
       * Limpa o formulário após envio bem-sucedido.
       * 
       * reset():
       * - Limpa todos os campos do formulário
       * - Retorna inputs aos valores padrão
       * - Remove estados de validação
       * 
       * Permite usuário tentar novamente com outro email se necessário.
       */
      resetForm.reset();
      
    } catch (error) {
      // ===== ETAPA 5B: ERRO - TRATAMENTO E FEEDBACK =====
      /**
       * Captura erros lançados pelo Firebase.
       * 
       * Tipos de erro possíveis:
       * - Rede (sem internet, timeout)
       * - Validação (email inválido, formato incorreto)
       * - Rate limiting (muitas tentativas)
       * - Configuração (Firebase mal configurado)
       */
      
      /**
       * Log do erro para debugging.
       * Útil para:
       * - Desenvolvimento local
       * - Identificação de problemas
       * - Monitoramento de erros
       * 
       * Em produção, considere enviar para serviço de logging (Sentry, etc).
       */
      console.error("Erro ao enviar email de redefinição:", error);
      
      /**
       * Mensagem genérica de erro (fallback).
       * Usada se erro não for reconhecido no switch.
       */
      let message = "Não foi possível enviar o link. Tente novamente.";
      
      // ===== MAPEAMENTO DE ERROS FIREBASE =====
      /**
       * Converte códigos técnicos do Firebase em mensagens amigáveis.
       * 
       * error.code: string com código do erro Firebase
       * Formato: "auth/nome-do-erro"
       * 
       * Switch statement:
       * - Mais eficiente que múltiplos if/else
       * - Fácil adicionar novos casos
       * - Organizado e legível
       */
      switch (error.code) {
        /**
         * auth/missing-email:
         * Email não foi fornecido na requisição.
         * Teoricamente não deve acontecer devido à validação anterior.
         */
        case "auth/missing-email":
          message = "Informe um e-mail antes de continuar.";
          break;
        
        /**
         * auth/invalid-email:
         * Formato de email inválido.
         * Exemplo: "usuario@" (sem domínio), "usuario" (sem @)
         */
        case "auth/invalid-email":
          message = "Formato de e-mail inválido.";
          break;
        
        /**
         * auth/user-not-found:
         * Email não cadastrado no sistema.
         * 
         * Nota de segurança:
         * Dependendo da configuração, Firebase pode não retornar este erro
         * para prevenir enumeração de usuários.
         */
        case "auth/user-not-found":
          message = "Não encontramos uma conta com este e-mail.";
          break;
        
        /**
         * auth/network-request-failed:
         * Falha de conexão com servidor Firebase.
         * Causas: sem internet, firewall, proxy, DNS
         */
        case "auth/network-request-failed":
          message = "Falha de conexão. Verifique sua internet.";
          break;
        
        /**
         * auth/too-many-requests:
         * Rate limit excedido (muitas tentativas).
         * Proteção automática do Firebase contra abuso.
         */
        case "auth/too-many-requests":
          message = "Muitas tentativas. Aguarde alguns minutos e tente novamente.";
          break;
        
        /**
         * default:
         * Qualquer outro erro não mapeado.
         * Usa mensagem genérica de fallback.
         */
        default:
          message = "Erro ao enviar link. Tente novamente em instantes.";
      }
      
      /**
       * Exibe popup vermelho com mensagem de erro.
       * Usuário sabe o que deu errado e pode tomar ação apropriada.
       */
      showPopup(message, "red");
      
    } finally {
      // ===== ETAPA 6: DESATIVAR LOADING STATE =====
      /**
       * Bloco finally: executa sempre, com ou sem erro.
       * 
       * Propósito:
       * - Garante que botão seja reabilitado
       * - Mesmo em caso de erro, usuário pode tentar novamente
       * - Previne botão ficar "travado" em loading
       * 
       * setLoadingState(false):
       * - Habilita botão
       * - Volta texto para "Enviar link"
       * - Restaura cursor pointer
       */
      setLoadingState(false);
    }
  });
} else {
  // ===== AVISO: FORMULÁRIO NÃO ENCONTRADO =====
  /**
   * Guard clause global: alerta se formulário não existir.
   * 
   * console.warn:
   * - Não interrompe execução (diferente de throw)
   * - Visível no DevTools
   * - Útil para debugging
   * 
   * Cenários possíveis:
   * - Script carregado em página errada
   * - ID do formulário foi alterado no HTML
   * - Erro de digitação no getElementById
   * - HTML ainda não carregou (script executou antes do DOM)
   */
  console.warn("Formulário de recuperação de senha não encontrado.");
}

/**
 * ============================================================
 * NOTAS TÉCNICAS E MELHORES PRÁTICAS
 * ============================================================
 * 
 * Configuração no Firebase Console:
 * 1. Acessar: Authentication > Templates > Password reset
 * 2. Personalizar template do email:
 *    - Assunto
 *    - Corpo da mensagem
 *    - Estilo CSS
 * 3. Definir URL de redirecionamento:
 *    - URL da página que processará o reset
 *    - Geralmente: auth.html ou reset-password.html
 * 4. Testar envio de email
 * 
 * Rate Limiting:
 * - Firebase limita automaticamente envios por IP
 * - Máximo: ~5 emails por hora por endereço IP
 * - Proteção contra spam e abuso
 * - Não requer configuração adicional
 * 
 * Expiração do Link:
 * - Padrão: 1 hora após envio
 * - Configurável no Firebase Console
 * - Link inválido após uso (single-use)
 * - Novo link invalida links anteriores
 * 
 * Segurança:
 * - NUNCA revelar se email existe ou não
 * - Sempre mostrar mensagem de sucesso genérica
 * - Logs detalhados apenas server-side
 * - Não expor detalhes técnicos ao usuário
 * 
 * Acessibilidade:
 * - Mensagens claras e diretas
 * - Cores semânticas (verde=sucesso, vermelho=erro)
 * - Loading state visível
 * - Botão desabilitado durante processamento
 * 
 * Testes Recomendados:
 * 1. Email válido cadastrado: deve receber email
 * 2. Email válido não cadastrado: sucesso genérico
 * 3. Email inválido: erro de formato
 * 4. Campo vazio: erro de validação
 * 5. Sem internet: erro de conexão
 * 6. Múltiplos envios rápidos: rate limit
 * 
 * Melhorias Futuras:
 * - Adicionar reCAPTCHA (prevenir bots)
 * - Contador de tentativas visual
 * - Sugestão de alteração de email ("Quis dizer...?")
 * - Integração com analytics (rastrear sucesso/erro)
 * - A/B testing de mensagens
 * 
 * Referências:
 * - Firebase Auth Docs: https://firebase.google.com/docs/auth
 * - sendPasswordResetEmail: https://firebase.google.com/docs/auth/web/manage-users#send_a_password_reset_email
 * - Error Codes: https://firebase.google.com/docs/reference/js/auth#autherrorcodes
 */
