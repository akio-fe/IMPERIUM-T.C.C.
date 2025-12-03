/**
 * ============================================================
 * MÓDULO: Manipulador de Ações de Autenticação
 * ============================================================
 * 
 * Propósito:
 * Processa ações de autenticação enviadas por email do Firebase.
 * Gerencia reset de senha e verificação de email.
 * 
 * Funcionalidades:
 * - Reset de senha (mode=resetPassword)
 * - Verificação de email (mode=verifyEmail)
 * - Sincronização de dados com backend PHP
 * - Migração de dados temporários do Firestore para MySQL
 * 
 * Fluxo de Verificação de Email:
 * 1. Usuário se cadastra → Firebase envia email
 * 2. Usuário clica no link → redireciona para esta página
 * 3. Script valida oobCode (one-time code)
 * 4. Busca dados temporários no Firestore (/unverified_users/{uid})
 * 5. Envia dados para backend PHP (/checkout/checkout.php)
 * 6. Backend salva no MySQL
 * 7. Remove dados temporários do Firestore
 * 8. Redireciona para página de login
 * 
 * Fluxo de Reset de Senha:
 * 1. Usuário solicita reset → Firebase envia email
 * 2. Usuário clica no link → redireciona para esta página
 * 3. Usuário digita nova senha
 * 4. Firebase valida código e atualiza senha
 * 5. Redireciona para login
 * 
 * Parâmetros de URL:
 * - mode: tipo de ação (resetPassword, verifyEmail)
 * - oobCode: código de verificação único
 * - apiKey: chave da API Firebase (validação)
 * 
 * Tecnologias:
 * - Firebase Authentication 9.23.0 (modular SDK)
 * - Firebase Firestore (armazenamento temporário)
 * - Fetch API (comunicação com backend)
 * 
 * Dependências HTML:
 * <h2 id="authTitle">Título dinâmico</h2>
 * <p id="authMessage">Mensagem dinâmica</p>
 */

// ===== IMPORTAÇÕES DO FIREBASE =====
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
/**
 * Módulo de autenticação Firebase.
 * 
 * Métodos importados:
 * - getAuth: obtém instância do serviço de autenticação
 * - confirmPasswordReset: confirma nova senha após reset
 * - verifyPasswordResetCode: valida código de reset antes de alterar senha
 * - applyActionCode: aplica ação de email (verificação, etc)
 * - onAuthStateChanged: observa mudanças no estado de autenticação
 * - reload: recarrega dados do usuário autenticado
 */
import {
  getAuth,
  confirmPasswordReset,
  verifyPasswordResetCode,
  applyActionCode,
  onAuthStateChanged,
  reload,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";
/**
 * Módulo do Firebase Firestore (banco NoSQL).
 * 
 * Usado para armazenar dados temporários de usuários não verificados.
 * 
 * Estrutura:
 * /unverified_users/{uid}/
 *   - nome: string
 *   - cpf: string
 *   - tel: string | null
 *   - datanasc: string | null
 *   - email: string
 *   - uid: string
 * 
 * Métodos:
 * - getFirestore: obtém instância do Firestore
 * - doc: cria referência a documento
 * - getDoc: busca documento
 * - deleteDoc: remove documento (após migração para MySQL)
 */
import {
  getFirestore,
  doc,
  getDoc,
  deleteDoc,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====
/**
 * Credenciais do projeto Firebase.
 * Mesmas credenciais usadas em cadastro_login.js.
 * 
 * Segurança:
 * - apiKey é pública (apenas identifica projeto)
 * - Regras do Firestore protegem dados sensíveis
 * - Backend PHP valida tokens JWT
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

// ===== INICIALIZAÇÃO DOS SERVIÇOS =====
/**
 * Instancia serviços Firebase:
 * - app: aplicação configurada
 * - auth: serviço de autenticação
 * - db: banco Firestore (dados temporários)
 */
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

// ===== RESOLUÇÃO DE CAMINHOS =====
/**
 * Determina o caminho raiz da pasta /public/ dinamicamente.
 * 
 * Útil para aplicações que podem estar em subpastas.
 * 
 * Exemplos:
 * - URL: http://localhost/imperium/public/pages/auth/handler.html
 * - Retorno: "/imperium/public"
 * 
 * - URL: http://localhost/public/pages/auth/handler.html
 * - Retorno: "/public"
 * 
 * @returns {string} - Caminho base da pasta public
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
 * URLs da aplicação:
 * - PUBLIC_ROOT: base da pasta public (/imperium/public)
 * - PROJECT_ROOT: raiz do projeto (/imperium)
 * - AUTH_PAGE: página de login/cadastro
 * - CHECKOUT_ENDPOINT: API para salvar usuário no MySQL
 * - HOME_URL: página inicial após login
 */
const PUBLIC_ROOT = resolvePublicRoot();
const PROJECT_ROOT = PUBLIC_ROOT.replace(/\/public$/, "");
const AUTH_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;
const CHECKOUT_ENDPOINT = `${PUBLIC_ROOT}/pages/checkout/checkout.php`;
const HOME_URL = `${PROJECT_ROOT || ""}/index.php`;

// ===== INICIALIZAÇÃO DA PÁGINA =====
/**
 * Aguarda carregamento completo do DOM.
 * 
 * Fluxo:
 * 1. Busca elementos do DOM (título e mensagem)
 * 2. Extrai parâmetros da URL (mode e oobCode)
 * 3. Valida presença de parâmetros obrigatórios
 * 4. Roteia para função apropriada baseado no modo
 */
document.addEventListener("DOMContentLoaded", function () {
  const authTitle = document.getElementById("authTitle");
  const authMessage = document.getElementById("authMessage");
  
  // Extrai parâmetros do link de email do Firebase
  const urlParams = new URLSearchParams(window.location.search);
  const mode = urlParams.get("mode"); // resetPassword | verifyEmail
  const oobCode = urlParams.get("oobCode"); // Código único de verificação

  // ===== VALIDAÇÃO DE PARÂMETROS =====
  /**
   * Verifica se link contém parâmetros necessários.
   * 
   * Parâmetros obrigatórios:
   * - mode: tipo de ação a executar
   * - oobCode: código de verificação único (one-time-use)
   * 
   * Exemplo de URL válida:
   * handler.html?mode=verifyEmail&oobCode=ABC123...
   */
  if (!mode || !oobCode) {
    authTitle.textContent = "Erro";
    authMessage.textContent =
      "Link de autenticação inválido. Por favor, tente novamente.";
    return;
  }

  // ===== ROTEAMENTO DE AÇÕES =====
  /**
   * Direciona para função apropriada baseado no modo.
   * 
   * Modos suportados:
   * - resetPassword: usuário solicitou nova senha
   * - verifyEmail: usuário precisa confirmar email
   * 
   * Caso contrário: redireciona para home após 3 segundos
   */
  switch (mode) {
    case "resetPassword":
      handleResetPassword(oobCode);
      break;
    case "verifyEmail":
      handleVerifyEmail(oobCode);
      break;
    default:
      authTitle.textContent = "Ação desconhecida";
      authMessage.textContent =
        "Ação de autenticação não reconhecida. Redirecionando...";
      setTimeout(() => (window.location.href = HOME_URL), 3000);
      break;
  }

  // ===== FUNÇÃO: VERIFICAÇÃO DE EMAIL =====
  /**
   * Processa verificação de email após cadastro.
   * 
   * Fluxo completo:
   * 1. Aplica código de verificação no Firebase
   * 2. Verifica se usuário está autenticado
   * 3. Busca dados temporários no Firestore
   * 4. Envia dados para backend PHP
   * 5. Backend salva no MySQL
   * 6. Remove dados temporários do Firestore
   * 7. Redireciona para login
   * 
   * Tratamento de erros:
   * - Código inválido/expirado
   * - Usuário não encontrado
   * - Dados temporários ausentes
   * - Falha na comunicação com backend
   * - Resposta não-JSON do servidor
   * 
   * @param {string} oobCode - Código de verificação único
   */
  async function handleVerifyEmail(oobCode) {
    authTitle.textContent = "Verificação de E-mail";
    authMessage.textContent = "Verificando sua conta...";

    try {
      // ===== ETAPA 1: APLICAR CÓDIGO DE VERIFICAÇÃO =====
      /**
       * applyActionCode valida o código e marca email como verificado.
       * 
       * Possíveis erros:
       * - auth/expired-action-code: link expirou (geralmente 1 hora)
       * - auth/invalid-action-code: código inválido ou já usado
       * - auth/user-disabled: conta foi desativada
       */
      await applyActionCode(auth, oobCode);

      // ===== ETAPA 2: VERIFICAR AUTENTICAÇÃO =====
      /**
       * Verifica se usuário está autenticado no navegador.
       * 
       * Caso não esteja: usuário precisa fazer login manualmente.
       * Isso pode acontecer se:
       * - Usuário abriu link em navegador diferente
       * - Cookies/sessão foram limpos
       * - Muito tempo passou desde o cadastro
       */
      const user = auth.currentUser;
      if (!user) {
        authMessage.textContent =
          "Sessão de usuário não encontrada. Por favor, faça login para continuar.";
        setTimeout(() => (window.location.href = AUTH_PAGE), 3000);
        return;
      }

      // ===== ETAPA 3: BUSCAR DADOS TEMPORÁRIOS =====
      /**
       * Busca dados salvos durante cadastro no Firestore.
       * 
       * Localização: /unverified_users/{uid}
       * 
       * Campos esperados:
       * - nome: nome completo
       * - cpf: documento
       * - tel: telefone (opcional)
       * - datanasc: data de nascimento (opcional)
       * - email: email do usuário
       * - uid: ID único do Firebase
       */
      const docRef = doc(db, "unverified_users", user.uid);
      const docSnap = await getDoc(docRef);

      if (docSnap.exists()) {
        const userData = docSnap.data();

        // ===== ETAPA 4: ENVIAR DADOS PARA BACKEND PHP =====
        /**
         * Envia dados para /checkout/checkout.php.
         * 
         * Backend fará:
         * - Validar token JWT
         * - Verificar se CPF já existe
         * - Inserir usuário no MySQL
         * - Retornar sucesso/erro
         * 
         * Headers importantes:
         * - Content-Type: application/json
         * - Authorization: Bearer {token JWT}
         */
        const idToken = await user.getIdToken();
        try {
          const response = await fetch(CHECKOUT_ENDPOINT, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Authorization: `Bearer ${idToken}`,
            },
            body: JSON.stringify(userData),
          });

          // ===== ETAPA 5: VALIDAR RESPOSTA DO BACKEND =====
          /**
           * Verifica se backend retornou JSON válido.
           * 
           * Problemas comuns:
           * - Backend retornou HTML de erro (500, 404)
           * - Warning/Notice do PHP antes do JSON
           * - Resposta vazia
           * 
           * Solução: ler resposta como texto primeiro para debug.
           */
          const contentType = response.headers.get("content-type");
          const rawBody = await response.text();

          /**
           * Valida Content-Type da resposta.
           * 
           * Backend PHP correto deve retornar:
           * Content-Type: application/json
           * 
           * Se retornar text/html: provavelmente erro PHP não capturado.
           */
          if (!contentType || !contentType.includes("application/json")) {
            console.error("Server returned non-JSON response:", rawBody);
            throw new Error("Unexpected server response format");
          }

          /**
           * Converte resposta de texto para objeto JSON.
           * 
           * Formato esperado:
           * {
           *   "success": true,
           *   "message": "Usuário criado com sucesso"
           * }
           * 
           * Ou em caso de erro:
           * {
           *   "success": false,
           *   "message": "CPF já cadastrado"
           * }
           */
          let result;
          try {
            result = JSON.parse(rawBody);
          } catch (parseError) {
            console.error("Server returned invalid JSON payload:", rawBody);
            throw new Error("Invalid JSON in server response");
          }

          // ===== ETAPA 6: PROCESSAR RESPOSTA =====
          if (result.success) {
            /**
             * Sucesso: usuário foi salvo no MySQL.
             * 
             * Ações:
             * 1. Exibir mensagem de sucesso
             * 2. Remover dados temporários do Firestore
             * 3. Redirecionar para login após 3 segundos
             */
            authMessage.textContent =
              "E-mail verificado e dados salvos! Redirecionando...";
            
            // Remove documento temporário (não precisa mais)
            await deleteDoc(docRef);
            
            // Redireciona para página de login
            setTimeout(() => (window.location.href = AUTH_PAGE), 3000);
          } else {
            /**
             * Falha: backend não conseguiu salvar.
             * 
             * Possíveis causas:
             * - CPF já existe no banco
             * - Erro de conexão com MySQL
             * - Dados inválidos
             * 
             * Nota: dados permanecem no Firestore para nova tentativa.
             */
            authMessage.textContent =
              "E-mail verificado, mas ocorreu um erro ao salvar dados. Entre em contato com o suporte.";
            console.error("Erro no servidor:", result.message);
          }
        } catch (error) {
          /**
           * Erro na comunicação com backend.
           * 
           * Causas possíveis:
           * - Servidor offline/inacessível
           * - Erro de rede (timeout, DNS)
           * - Resposta inválida (HTML em vez de JSON)
           * - CORS bloqueado
           * 
           * Email já foi verificado no Firebase,
           * mas dados não foram migrados para MySQL.
           * Usuário precisará contatar suporte.
           */
          authMessage.textContent =
            "E-mail verificado, mas ocorreu um erro ao comunicar com o servidor. Entre em contato com o suporte.";
          console.error("Erro ao enviar dados para o servidor:", error);
        }
      } else {
        /**
         * Documento temporário não encontrado no Firestore.
         * 
         * Possíveis causas:
         * - Usuário já verificou email anteriormente
         * - Documento foi removido manualmente
         * - Erro no cadastro inicial (não salvou no Firestore)
         * 
         * Email está verificado, mas sem dados para migrar.
         */
        authMessage.textContent =
          "E-mail verificado, mas dados adicionais não encontrados. Entre em contato com o suporte.";
      }
    } catch (error) {
      /**
       * Erro geral na verificação de email.
       * 
       * Principais erros Firebase:
       * - auth/expired-action-code: link expirou (>1 hora)
       * - auth/invalid-action-code: código inválido ou já usado
       * - auth/user-disabled: conta foi desativada
       * - auth/user-not-found: usuário foi deletado
       * 
       * Solução:
       * Usuário deve fazer login e solicitar novo email de verificação.
       */
      authTitle.textContent = "Erro na Verificação";
      authMessage.textContent =
        "O link de verificação é inválido ou já foi usado. Tente fazer login e reenviar o e-mail.";
      console.error(error);
    }
  }
});
