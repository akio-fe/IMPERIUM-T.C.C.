/**
 * ============================================================
 * MÓDULO: Manipulador de Ações de Autenticação Firebase
 * ============================================================
 * 
 * Propósito:
 * Processa links de ações enviados por email pelo Firebase Authentication.
 * Gerencia redefinição de senha e verificação de email.
 * 
 * Funcionalidades:
 * - Verificação de email (confirma conta após cadastro)
 * - Redefinição de senha (recuperação de conta)
 * - Sincronização com backend PHP (MySQL)
 * - Migração de dados temporários (Firestore → MySQL)
 * 
 * Fluxo de Verificação de Email:
 * 1. Usuário se cadastra (cadastro_login.js)
 * 2. Dados salvos em Firestore (coleção: unverified_users)
 * 3. Firebase envia email com link de verificação
 * 4. Usuário clica no link (redireciona para esta página)
 * 5. Este script aplica verificação no Firebase
 * 6. Busca dados em unverified_users/{uid}
 * 7. Envia dados para backend PHP (checkout.php)
 * 8. Backend salva em MySQL e cria sessão
 * 9. Exclui documento temporário do Firestore
 * 10. Redireciona usuário para login
 * 
 * Fluxo de Redefinição de Senha:
 * 1. Usuário esqueceu senha (esqueci.js)
 * 2. Firebase envia email com link de redefinição
 * 3. Usuário clica no link (redireciona para esta página)
 * 4. Este script valida código (oobCode)
 * 5. Permite usuário definir nova senha
 * 6. Atualiza senha no Firebase Authentication
 * 
 * Tecnologias:
 * - Firebase Authentication 9.23.0 (modular SDK)
 * - Firebase Firestore (dados temporários)
 * - Fetch API (comunicação com backend PHP)
 * 
 * Parâmetros URL Esperados:
 * - mode: tipo de ação ("verifyEmail" ou "resetPassword")
 * - oobCode: código único de verificação gerado pelo Firebase
 * 
 * Exemplo de URL:
 * https://site.com/auth-handler.html?mode=verifyEmail&oobCode=ABC123...
 */

// ===== IMPORTAÇÕES DO FIREBASE APP =====
/**
 * SDK modular do Firebase (v9.23.0).
 * initializeApp: inicializa aplicação com credenciais.
 */
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";

// ===== IMPORTAÇÕES DO FIREBASE AUTHENTICATION =====
/**
 * Módulo de autenticação Firebase.
 * 
 * Métodos utilizados:
 * - getAuth: obtém instância do serviço de autenticação
 * - confirmPasswordReset: confirma nova senha após redefinição
 * - verifyPasswordResetCode: valida código de redefinição
 * - applyActionCode: aplica ação de email (verificação, recuperação)
 * - onAuthStateChanged: monitora mudanças no estado de autenticação
 * - reload: recarrega dados do usuário atual
 */
import {
  getAuth,
  confirmPasswordReset,
  verifyPasswordResetCode,
  applyActionCode,
  onAuthStateChanged,
  reload,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// ===== IMPORTAÇÕES DO FIRESTORE =====
/**
 * Banco de dados NoSQL do Firebase.
 * 
 * Usado para armazenar dados temporários de usuários não verificados.
 * 
 * Estrutura:
 * /unverified_users/{uid}/
 *   - nome: "João Silva"
 *   - cpf: "123.456.789-00"
 *   - tel: "(11) 98765-4321"
 *   - datanasc: "1990-01-15"
 *   - email: "joao@example.com"
 *   - uid: "firebaseUserId123"
 * 
 * Métodos:
 * - getFirestore: obtém instância do Firestore
 * - doc: cria referência a documento específico
 * - getDoc: busca dados de um documento
 * - deleteDoc: exclui documento (após migração para MySQL)
 */
import {
  getFirestore,
  doc,
  getDoc,
  deleteDoc,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====
/**
 * Credenciais do projeto Firebase (console.firebase.google.com).
 * 
 * Campos:
 * - apiKey: chave pública da API (seguro expor no front-end)
 * - authDomain: domínio para autenticação OAuth
 * - projectId: identificador único do projeto (imperium-0001)
 * - storageBucket: bucket para armazenamento de arquivos
 * - messagingSenderId: ID para Firebase Cloud Messaging
 * - appId: identificador da aplicação web
 * - measurementId: Google Analytics (opcional)
 * 
 * Segurança:
 * - apiKey não é secreta (apenas identifica projeto)
 * - Segurança real vem de Firebase Rules no Firestore/Storage
 * - Rules controlam quem pode ler/escrever dados
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

// ===== INICIALIZAÇÃO DOS SERVIÇOS FIREBASE =====
/**
 * Instancia serviços Firebase para uso na aplicação.
 * 
 * Objetos criados:
 * - app: aplicação Firebase configurada
 * - auth: serviço de autenticação (gerencia sessões)
 * - db: instância do Firestore (banco NoSQL)
 */
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

// ===== FUNÇÃO UTILITÁRIA: RESOLUÇÃO DE CAMINHOS =====
/**
 * Detecta a pasta /public/ na URL e retorna o caminho base.
 * 
 * Cenários:
 * 1. URL: https://site.com/TCC/public/pages/auth/handler.html
 *    Retorna: "/TCC/public"
 * 
 * 2. URL: https://site.com/pages/auth/handler.html (sem /public/)
 *    Retorna: ""
 * 
 * Uso:
 * - Construir caminhos relativos para APIs e páginas
 * - Funciona em diferentes ambientes (dev, prod)
 * - Evita hardcoding de URLs
 * 
 * @returns {string} - Caminho base até /public ou string vazia
 */
const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

// ===== CONSTANTES DE NAVEGAÇÃO =====
/**
 * URLs usadas para redirecionamentos e APIs.
 * 
 * PUBLIC_ROOT: caminho base da pasta public
 * PROJECT_ROOT: raiz do projeto (remove /public do caminho)
 * AUTH_PAGE: página de login/cadastro
 * CHECKOUT_ENDPOINT: API PHP que recebe dados pós-verificação
 * HOME_URL: página inicial (index.php)
 * 
 * Exemplo de valores:
 * - PUBLIC_ROOT: "/TCC/public"
 * - PROJECT_ROOT: "/TCC"
 * - AUTH_PAGE: "/TCC/public/pages/auth/cadastro_login.html"
 * - CHECKOUT_ENDPOINT: "/TCC/public/pages/checkout/checkout.php"
 * - HOME_URL: "/TCC/index.php"
 */
const PUBLIC_ROOT = resolvePublicRoot();
const PROJECT_ROOT = PUBLIC_ROOT.replace(/\/public$/, "");
const AUTH_PAGE = `${PUBLIC_ROOT}/pages/auth/cadastro_login.html`;
const CHECKOUT_ENDPOINT = `${PUBLIC_ROOT}/pages/checkout/checkout.php`;
const HOME_URL = `${PROJECT_ROOT || ""}/index.php`;

// ===== INICIALIZAÇÃO: PROCESSAMENTO DE AÇÕES DE EMAIL =====
/**
 * Event listener executado quando a página carrega completamente.
 * 
 * Responsabilidade:
 * - Extrair parâmetros da URL (mode e oobCode)
 * - Validar presença de parâmetros obrigatórios
 * - Rotear para função correspondente (verificação ou redefinição)
 * - Exibir mensagens de feedback ao usuário
 * 
 * Parâmetros URL:
 * - mode: tipo de ação ("verifyEmail" ou "resetPassword")
 * - oobCode: código único e temporário do Firebase
 * 
 * HTML esperado:
 * <h1 id="authTitle">Verificando...</h1>
 * <p id="authMessage">Aguarde...</p>
 */
document.addEventListener("DOMContentLoaded", function () {
  // ===== REFERÊNCIAS DOS ELEMENTOS HTML =====
  /**
   * Elementos usados para feedback visual ao usuário.
   * 
   * authTitle: título principal (ex: "Verificação de E-mail")
   * authMessage: mensagem de status (ex: "Verificando sua conta...")
   */
  const authTitle = document.getElementById("authTitle");
  const authMessage = document.getElementById("authMessage");
  
  // ===== EXTRAÇÃO DOS PARÂMETROS DA URL =====
  /**
   * Usa URLSearchParams para ler query string.
   * 
   * Exemplo de URL:
   * https://site.com/handler.html?mode=verifyEmail&oobCode=ABC123
   * 
   * Extração:
   * - mode = "verifyEmail"
   * - oobCode = "ABC123"
   */
  const urlParams = new URLSearchParams(window.location.search);
  const mode = urlParams.get("mode");
  const oobCode = urlParams.get("oobCode");

  // ===== VALIDAÇÃO DE PARÂMETROS =====
  /**
   * Verifica se parâmetros obrigatórios estão presentes.
   * 
   * Cenários de erro:
   * - URL sem parâmetros
   * - Link expirado ou corrompido
   * - Acesso direto à página sem link de email
   * 
   * Tratamento:
   * - Exibe mensagem de erro
   * - Impede execução de código subsequente (return)
   */
  if (!mode || !oobCode) {
    authTitle.textContent = "Erro";
    authMessage.textContent =
      "Link de autenticação inválido. Por favor, tente novamente.";
    return;
  }

  // ===== ROTEAMENTO POR TIPO DE AÇÃO =====
  /**
   * Direciona para função específica baseado no parâmetro 'mode'.
   * 
   * Ações suportadas:
   * - resetPassword: redefinição de senha (não implementado neste arquivo)
   * - verifyEmail: verificação de email após cadastro
   * 
   * Default:
   * - Exibe erro para ações desconhecidas
   * - Redireciona para home após 3 segundos
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

  // ===== FUNÇÃO: VERIFICAÇÃO DE E-MAIL =====
  /**
   * Processa verificação de email após cadastro.
   * 
   * Fluxo completo:
   * 1. Aplica código de verificação no Firebase (applyActionCode)
   * 2. Verifica se usuário está autenticado
   * 3. Busca dados temporários no Firestore (unverified_users)
   * 4. Envia dados para backend PHP (checkout.php)
   * 5. Backend salva em MySQL e cria sessão
   * 6. Exclui dados temporários do Firestore
   * 7. Redireciona para página de login
   * 
   * Tratamento de Erros:
   * - Link inválido ou expirado
   * - Usuário não autenticado
   * - Dados temporários não encontrados
   * - Falha na comunicação com backend
   * - Resposta não-JSON do servidor
   * 
   * Segurança:
   * - Usa token JWT (idToken) para autenticar requisição ao backend
   * - Backend valida token com Firebase Admin SDK
   * - Evita falsificação de identidade
   * 
   * @param {string} oobCode - Código de verificação do Firebase
   */
  async function handleVerifyEmail(oobCode) {
    // Atualiza interface com status inicial
    authTitle.textContent = "Verificação de E-mail";
    authMessage.textContent = "Verificando sua conta...";

    try {
      // ===== PASSO 1: APLICAR VERIFICAÇÃO NO FIREBASE =====
      /**
       * applyActionCode marca o email como verificado no Firebase Auth.
       * 
       * Após esta chamada:
       * - user.emailVerified = true (no Firebase)
       * - Usuário pode fazer login normalmente
       * 
       * Pode lançar erro se:
       * - Código inválido ou expirado
       * - Código já foi usado anteriormente
       */
      await applyActionCode(auth, oobCode);

      // ===== PASSO 2: VERIFICAR SESSÃO DO USUÁRIO =====
      /**
       * Após applyActionCode, usuário deve estar autenticado.
       * 
       * auth.currentUser contém dados do usuário logado ou null.
       * 
       * Cenário de erro:
       * - Usuário fez logout antes de verificar email
       * - Sessão expirou
       * 
       * Solução:
       * - Redireciona para página de login
       * - Usuário deve fazer login manualmente
       */
      const user = auth.currentUser;
      if (!user) {
        authMessage.textContent =
          "Sessão de usuário não encontrada. Por favor, faça login para continuar.";
        setTimeout(() => (window.location.href = AUTH_PAGE), 3000);
        return;
      }

      // ===== PASSO 3: BUSCAR DADOS TEMPORÁRIOS NO FIRESTORE =====
      /**
       * Dados do usuário foram salvos temporariamente durante o cadastro.
       * 
       * Estrutura do documento:
       * /unverified_users/{uid}/
       *   - nome: "João Silva"
       *   - cpf: "123.456.789-00"
       *   - tel: "(11) 98765-4321"
       *   - datanasc: "1990-01-15"
       *   - email: "joao@example.com"
       *   - uid: "firebaseUserId123"
       * 
       * Por que usar Firestore temporariamente?
       * - Firebase Rules impede acesso não autorizado
       * - Dados ficam seguros até verificação
       * - Evita spam/bots criando contas falsas no MySQL
       */
      const docRef = doc(db, "unverified_users", user.uid);
      const docSnap = await getDoc(docRef);

      if (docSnap.exists()) {
        const userData = docSnap.data();

        // ===== PASSO 4: ENVIAR DADOS PARA BACKEND PHP =====
        /**
         * Backend (checkout.php) recebe dados e salva no MySQL.
         * 
         * Headers:
         * - Content-Type: indica JSON no body
         * - Authorization: Bearer token JWT para autenticação
         * 
         * Body:
         * - JSON com dados do usuário do Firestore
         * 
         * Backend verifica:
         * - Token JWT válido (Firebase Admin SDK)
         * - CPF único no banco de dados
         * - Cria registro em tabelas: usuarios, pessoas, clientes
         * - Cria sessão PHP para login automático
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

          // ===== VALIDAÇÃO DA RESPOSTA DO SERVIDOR =====
          /**
           * Verifica se resposta é JSON válido.
           * 
           * Problemas comuns:
           * - PHP retorna HTML de erro ao invés de JSON
           * - Echo/var_dump acidental antes do JSON
           * - Erro de sintaxe PHP
           * 
           * Solução:
           * - Lê resposta como texto primeiro
           * - Verifica Content-Type header
           * - Tenta fazer parse do JSON
           * - Loga resposta bruta em caso de erro (para debug)
           */
          const contentType = response.headers.get("content-type");
          const rawBody = await response.text();

          if (!contentType || !contentType.includes("application/json")) {
            console.error("Server returned non-JSON response:", rawBody);
            throw new Error("Unexpected server response format");
          }

          let result;
          try {
            result = JSON.parse(rawBody);
          } catch (parseError) {
            console.error("Server returned invalid JSON payload:", rawBody);
            throw new Error("Invalid JSON in server response");
          }

          // ===== PROCESSAMENTO DO RESULTADO =====
          /**
           * Backend retorna: { success: true/false, message: "..." }
           * 
           * Sucesso:
           * - Usuário salvo no MySQL
           * - Sessão PHP criada
           * - Dados temporários podem ser excluídos
           * 
           * Falha:
           * - CPF duplicado
           * - Erro no banco de dados
           * - Token inválido
           */
          if (result.success) {
            authMessage.textContent =
              "E-mail verificado e dados salvos! Redirecionando...";
            
            // ===== PASSO 5: LIMPAR DADOS TEMPORÁRIOS =====
            /**
             * Exclui documento do Firestore após migração bem-sucedida.
             * 
             * Motivos:
             * - Dados já estão no MySQL
             * - Libera espaço no Firestore
             * - Previne processamento duplicado
             * - Segurança: remove dados sensíveis não mais necessários
             */
            await deleteDoc(docRef);
            
            // Redireciona usuário para login após 3 segundos
            setTimeout(() => (window.location.href = AUTH_PAGE), 3000);
          } else {
            authMessage.textContent =
              "E-mail verificado, mas ocorreu um erro ao salvar dados. Entre em contato com o suporte.";
            console.error("Erro no servidor:", result.message);
          }
        } catch (error) {
          authMessage.textContent =
            "E-mail verificado, mas ocorreu um erro ao comunicar com o servidor. Entre em contato com o suporte.";
          console.error("Erro ao enviar dados para o servidor:", error);
        }
      } else {
        // ===== ERRO: DADOS TEMPORÁRIOS NÃO ENCONTRADOS =====
        /**
         * Cenários possíveis:
         * - Documento já foi excluído em verificação anterior
         * - Usuário criado por outro método (sem Firestore)
         * - Falha no cadastro inicial
         * 
         * Ação:
         * - Informa usuário para contatar suporte
         * - Admin deve verificar logs e banco de dados
         */
        authMessage.textContent =
          "E-mail verificado, mas dados adicionais não encontrados. Entre em contato com o suporte.";
      }
    } catch (error) {
      // ===== TRATAMENTO DE ERROS GERAIS =====
      /**
       * Captura erros de applyActionCode ou outros.
       * 
       * Erros comuns:
       * - auth/invalid-action-code: código inválido/expirado
       * - auth/expired-action-code: código expirado (24h)
       * - auth/user-disabled: conta desabilitada por admin
       * 
       * Tratamento:
       * - Exibe mensagem genérica ao usuário
       * - Loga erro detalhado no console (para debug)
       */
      authTitle.textContent = "Erro na Verificação";
      authMessage.textContent =
        "O link de verificação é inválido ou já foi usado. Tente fazer login e reenviar o e-mail.";
      console.error(error);
    }
  }
});
