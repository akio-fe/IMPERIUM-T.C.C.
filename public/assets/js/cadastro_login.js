/**
 * ============================================================
 * MÓDULO: Autenticação e Cadastro de Usuários
 * ============================================================
 * 
 * Propósito:
 * Gerencia autenticação completa de usuários usando Firebase Auth.
 * Suporta cadastro com email/senha e login social (Google).
 * 
 * Funcionalidades:
 * - Cadastro com verificação de email
 * - Login com email/senha
 * - Login social com Google (OAuth 2.0)
 * - Sincronização de dados com Firestore
 * - Integração com backend PHP via API
 * 
 * Fluxo de Autenticação:
 * 1. Usuário autentica no Firebase (client-side)
 * 2. Firebase retorna token JWT
 * 3. Token enviado para backend PHP (/api/auth/login.php)
 * 4. Backend valida token e cria sessão PHP
 * 5. Dados do usuário enriquecidos com banco MySQL
 * 
 * Tecnologias:
 * - Firebase Authentication 9.6.1 (modular SDK)
 * - Firebase Firestore (armazenamento NoSQL)
 * - ES6 Modules (import/export)
 */

// ===== IMPORTAÇÕES DO FIREBASE APP =====
/**
 * SDK modular do Firebase (v9+).
 * initializeApp: inicializa aplicação Firebase com credenciais.
 */
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.6.1/firebase-app.js";

// ===== IMPORTAÇÕES DO FIREBASE AUTHENTICATION =====
/**
 * Módulo de autenticação Firebase.
 * 
 * Métodos importados:
 * - getAuth: obtém instância do serviço de autenticação
 * - createUserWithEmailAndPassword: cria nova conta com email/senha
 * - sendEmailVerification: envia email de verificação
 * - signInWithEmailAndPassword: login tradicional
 * - signInWithPopup: login social via popup (Google, Facebook, etc)
 * - GoogleAuthProvider: provedor OAuth do Google
 */
import {
  getAuth,
  createUserWithEmailAndPassword,
  sendEmailVerification,
  signInWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
} from "https://www.gstatic.com/firebasejs/9.6.1/firebase-auth.js";

// ===== IMPORTAÇÕES DO FIRESTORE =====
/**
 * Banco de dados NoSQL do Firebase.
 * 
 * Usado para armazenar dados complementares do usuário:
 * - Nome completo
 * - CPF
 * - Telefone
 * - Data de nascimento
 * 
 * Estrutura:
 * /usuarios/{uid}/
 *   - nome: "João Silva"
 *   - cpf: "123.456.789-00"
 *   - telefone: "(11) 98765-4321"
 *   - dataNascimento: "1990-01-15"
 * 
 * Métodos:
 * - getFirestore: obtém instância do Firestore
 * - doc: referência a documento específico
 * - setDoc: cria/atualiza documento
 */
import {
  getFirestore,
  doc,
  setDoc,
} from "https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore.js";

import { firebaseConfig } from "./firebase-config.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====
/**
 * Credenciais do projeto Firebase (console.firebase.google.com).
 * 
 * Campos:
 * - apiKey: chave pública da API (seguro expor no front-end)
 * - authDomain: domínio para autenticação OAuth
 * - projectId: identificador único do projeto
 * - storageBucket: bucket para armazenamento de arquivos
 * - messagingSenderId: ID para Firebase Cloud Messaging
 * - appId: identificador da aplicação web
 * - measurementId: Google Analytics (opcional)
 * 
 * Segurança:
 * - apiKey não é secreta (apenas identifica projeto)
 * - Segurança real vem de Firebase Rules no Firestore/Storage
 * - Autenticação protege recursos via regras de banco de dados
 */
// ===== INICIALIZAÇÃO DOS SERVIÇOS FIREBASE =====
/**
 * Instancia serviços Firebase para uso na aplicação.
 * 
 * Objetos criados:
 * - app: aplicação Firebase configurada
 * - auth: serviço de autenticação (login, cadastro, logout)
 * - googleProvider: provedor OAuth do Google
 * - db: instância do Firestore (banco NoSQL)
 * 
 * Todos esses objetos são usados nas funções de autenticação.
 */
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const googleProvider = new GoogleAuthProvider();
const db = getFirestore(app);

// ===== REFERÊNCIAS DOS ELEMENTOS HTML - FORMULÁRIO DE LOGIN =====
/**
 * Busca elementos do formulário de login no DOM.
 * 
 * HTML esperado:
 * <form id="formLogin">
 *   <input id="email-login" type="email">
 *   <input id="senha-login" type="password">
 *   <button type="submit">Entrar</button>
 * </form>
 * 
 * Uso:
 * loginForm.addEventListener('submit', ...) para capturar envio.
 */
const loginForm = document.getElementById("formLogin");
const emailInput = document.getElementById("email-login");
const passwordInput = document.getElementById("senha-login");

// ===== REFERÊNCIAS DOS ELEMENTOS HTML - FORMULÁRIO DE CADASTRO =====
/**
 * Busca elementos do formulário de cadastro no DOM.
 * 
 * Campos obrigatórios:
 * - nome: nome completo do usuário
 * - CPF: documento (validado com máscara)
 * - telefone: com DDD (máscara aplicada)
 * - data-nasc: data de nascimento (YYYY-MM-DD)
 * - email: email único (validado pelo Firebase)
 * - senha: mínimo 6 caracteres (regra Firebase)
 * 
 * Validações:
 * - Front-end: máscaras, campos obrigatórios, formato
 * - Firebase: email único, senha forte
 * - Backend: CPF único no MySQL
 */
const formCadastro = document.getElementById("formCadastro");
const nomeInputCadastro = document.getElementById("nome");
const cpfInputCadastro = document.getElementById("CPF");
const telInputCadastro = document.getElementById("telefone");
const dataNascInput = document.getElementById("data-nasc");
const emailInputCadastro = document.getElementById("email");
const senhaInputCadastro = document.getElementById("senha");
const senhaconfInput = document.getElementById("confirma_senha");

// ===== LISTENERS: BOTÕES DE LOGIN SOCIAL =====
/**
 * Configura botões de login com Google.
 * 
 * Múltiplos botões (googleButton e googleButton2):
 * Permite ter botões em diferentes locais da página (ex: topo e rodapé).
 * Ambos executam a mesma função signInWithGoogle().
 * 
 * Fluxo OAuth 2.0:
 * 1. Usuário clica no botão
 * 2. Abre popup do Google
 * 3. Usuário autoriza acesso
 * 4. Firebase retorna credenciais
 * 5. Backend recebe token e cria sessão
 */
document
  .getElementById("googleButton")
  .addEventListener("click", signInWithGoogle);

document
  .getElementById("googleButton2")
  .addEventListener("click", signInWithGoogle);

// ===== LISTENER: FORMULÁRIO DE LOGIN =====
/**
 * Gerencia envio do formulário de login tradicional (email/senha).
 * 
 * Fluxo:
 * 1. Previne envio padrão do formulário (preventDefault)
 * 2. Extrai email e senha dos inputs
 * 3. Autentica no Firebase
 * 4. Processa login no backend PHP
 * 5. Redireciona para home ou exibe erro
 * 
 * Tratamento de erros Firebase:
 * - auth/user-not-found: Email não cadastrado
 * - auth/wrong-password: Senha incorreta
 * - auth/invalid-email: Formato de email inválido
 * - Outros: Erro genérico
 */
if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Coleta dados do formulário
    const email = emailInput.value;
    const password = passwordInput.value;

    try {
      // Autentica no Firebase
      const userCredential = await signInWithEmailAndPassword(
        auth,
        email,
        password
      );
      const user = userCredential.user;

      // Processa login no backend (cria sessão PHP)
      await processBackendLogin(user);
    } catch (error) {
      // Log do erro para debug
      console.error("Erro de login:", error.code, error.message);

      // ===== MAPEAMENTO DE ERROS FIREBASE =====
      /**
       * Converte códigos técnicos do Firebase em mensagens amigáveis.
       * 
       * Códigos comuns:
       * - auth/user-not-found: Conta não existe
       * - auth/wrong-password: Senha incorreta
       * - auth/invalid-email: Email malformado
       * - auth/too-many-requests: Muitas tentativas falhas
       * - auth/user-disabled: Conta desativada
       */
      let errorMessage = "Ocorreu um erro no login.";
      switch (error.code) {
        case "auth/user-not-found":
          errorMessage = "Nenhum usuário encontrado com este e-mail.";
          break;
        case "auth/wrong-password":
          errorMessage = "Senha incorreta.";
          break;
        case "auth/invalid-email":
          errorMessage = "Formato de e-mail inválido.";
          break;
        default:
          errorMessage = "Erro desconhecido. Por favor, tente novamente.";
          break;
      }
      showPopup(errorMessage, "red");
    }
  });
} else {
  console.warn("Elemento #formLogin não encontrado na página atual.");
}

// ===== LISTENER: FORMULÁRIO DE CADASTRO =====
/**
 * Gerencia envio do formulário de cadastro completo.
 * 
 * Fluxo de cadastro (3 etapas):
 * 1. Criar conta no Firebase Authentication
 * 2. Salvar dados complementares no Firestore (temporário)
 * 3. Enviar email de verificação
 * 4. Após verificação: migrar dados para MySQL
 * 
 * Validações:
 * - Senhas devem coincidir
 * - Email único (Firebase)
 * - CPF único (backend PHP)
 * - Campos obrigatórios: nome, email, senha, CPF
 * - Campos opcionais: telefone, data de nascimento
 */
formCadastro.addEventListener("submit", function (event) {
  event.preventDefault();

  // ===== COLETA DE DADOS DO FORMULÁRIO =====
  const email = emailInputCadastro.value.trim();
  const senha = senhaInputCadastro.value;
  const senhaconf = senhaconfInput.value;
  const nome = nomeInputCadastro.value.trim();
  const cpf = cpfInputCadastro.value.trim();
  const telefone = null; // Opcional no MVP
  const dataNasc = null; // Opcional no MVP

  // ===== VALIDAÇÃO: CONFIRMAÇÃO DE SENHA =====
  /**
   * Verifica se senha e confirmação são idênticas.
   * 
   * setCustomValidity:
   * Define mensagem de validação HTML5 personalizada.
   * Previne envio do formulário se não for vazia.
   */
  if (senha !== senhaconf) {
    senhaconfInput.setCustomValidity("As senhas não conferem");
    showPopup("As senhas não coincidem.", "red");
    return;
  } else {
    senhaconfInput.setCustomValidity(""); // Remove validação
  }

  // Feedback visual de loading
  showPopup("Processando cadastro...", "blue");

  // ===== ETAPA 1: CRIAR CONTA NO FIREBASE =====
  /**
   * createUserWithEmailAndPassword:
   * - Valida formato do email
   * - Verifica se email já existe
   * - Valida força da senha (mínimo 6 caracteres)
   * - Cria conta no Firebase Authentication
   * - Retorna UserCredential com dados do usuário
   */
  createUserWithEmailAndPassword(auth, email, senha)
    .then((userCredential) => {
      const user = userCredential.user;

      // ===== ETAPA 2: SALVAR DADOS NO FIRESTORE (TEMPORÁRIO) =====
      /**
       * Salva dados complementares no Firestore.
       * 
       * Por que Firestore temporário?
       * - Firebase não armazena nome, CPF, etc nativamente
       * - MySQL precisa de email verificado para criar registro
       * - Firestore serve como buffer até verificação
       * 
       * Coleção: unverified_users/{uid}
       * Após verificação: dados migram para MySQL e documento é deletado
       */
      return setDoc(doc(db, "unverified_users", user.uid), {
        nome: nome,
        cpf: cpf,
        tel: telefone,
        datanasc: dataNasc,
        email: user.email,
        uid: user.uid,
      });
    })
    .then(() => {
      // ===== ETAPA 3: ENVIAR EMAIL DE VERIFICAÇÃO =====
      /**
       * sendEmailVerification:
       * - Envia email com link único para verificação
       * - Link válido por 1 hora (padrão Firebase)
       * - Usuário não pode fazer login até verificar
       * 
       * Template do email:
       * Configurado no Firebase Console > Authentication > Templates
       */
      const user = auth.currentUser;
      if (user) {
        return sendEmailVerification(user);
      } else {
        throw new Error("Usuário não encontrado após o cadastro.");
      }
    })
    .then(() => {
      // ===== ETAPA 4: SUCESSO - AGUARDAR VERIFICAÇÃO =====
      /**
       * Cadastro completo no Firebase.
       * 
       * Próximos passos para o usuário:
       * 1. Abrir email
       * 2. Clicar no link de verificação
       * 3. auth-handler.js processa verificação
       * 4. Dados migram para MySQL
       * 5. Usuário pode fazer login
       */
      showPopup(
        "Cadastro realizado com sucesso! Um e-mail de verificação foi enviado. Por favor, verifique sua caixa de entrada para ativar sua conta.",
        "green"
      );
      formCadastro.reset();
    })
    .catch((error) => {
      // ===== TRATAMENTO DE ERROS DO CADASTRO =====
      /**
       * Mapeia erros Firebase/Firestore para mensagens amigáveis.
       * 
       * Erros comuns:
       * - auth/email-already-in-use: Email já cadastrado
       * - auth/weak-password: Senha < 6 caracteres
       * - auth/invalid-email: Email malformado
       * - permission-denied: Regras do Firestore bloquearam operação
       *   (verificar firestore.rules no Firebase Console)
       */
      const errorCode = error.code;
      let errorMessage =
        "Ocorreu um erro ao cadastrar. Por favor, tente novamente.";

      if (errorCode === "auth/email-already-in-use") {
        errorMessage = "Este e-mail já está em uso.";
      } else if (errorCode === "auth/weak-password") {
        errorMessage = "A senha deve ter pelo menos 6 caracteres.";
      } else if (errorCode === "auth/invalid-email") {
        errorMessage = "O endereço de e-mail é inválido.";
      } else if (errorCode === "permission-denied") {
        errorMessage =
          "Erro de permissão no Firestore. Verifique suas regras de segurança.";
      }

      showPopup(errorMessage, "red");
      console.error(errorCode, error.message);
    });
});

// ===== FUNÇÃO: LOGIN COM GOOGLE (OAUTH 2.0) =====
/**
 * Autentica usuário via conta Google.
 * 
 * Fluxo OAuth 2.0:
 * 1. signInWithPopup abre janela popup do Google
 * 2. Usuário seleciona conta e autoriza permissões
 * 3. Firebase recebe token OAuth do Google
 * 4. Firebase cria/autentica usuário automaticamente
 * 5. Retorna UserCredential com dados do Google
 * 6. Backend recebe token JWT e cria sessão
 * 
 * Vantagens:
 * - Sem necessidade de senha
 * - Email já verificado pelo Google
 * - Processo mais rápido
 * - Maior segurança (2FA do Google)
 * 
 * Dados obtidos do Google:
 * - Email (user.email)
 * - Nome (user.displayName)
 * - Foto (user.photoURL)
 * - ID único (user.uid)
 */
async function signInWithGoogle() {
  try {
    // Abre popup do Google OAuth
    const result = await signInWithPopup(auth, googleProvider);
    const user = result.user;
    
    // Processa login no backend (cria sessão PHP)
    await processBackendLogin(user);
  } catch (error) {
    /**
     * Erros comuns:
     * - auth/popup-closed-by-user: Usuário fechou popup
     * - auth/popup-blocked: Navegador bloqueou popup
     * - auth/account-exists-with-different-credential: Email já usado com outro método
     */
    const errorCode = error.code;
    const errorMessage = error.message;
    console.error("Erro de login com Google:", errorMessage);
    showPopup("Erro ao fazer login com o Google: " + errorMessage, "red");
  }
}

// ===== RESOLUÇÃO DINÂMICA DE CAMINHOS =====
/**
 * Determina caminho base da pasta /public/ dinamicamente.
 * 
 * Por que dinâmico?
 * Aplicação pode estar em diferentes estruturas:
 * - Produção: https://imperium.com/
 * - Desenvolvimento: http://localhost/imperium/
 * - Testes: http://localhost:8080/projeto/
 * 
 * Função busca "/public/" na URL e extrai base.
 * 
 * @returns {string} - Caminho da pasta public
 */
const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return ""; // Raiz do servidor
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

/**
 * URLs da aplicação construídas dinamicamente:
 * - PUBLIC_ROOT: /imperium/public
 * - PROJECT_ROOT: /imperium
 * - API_LOGIN_URL: /imperium/public/api/auth/login.php
 * - HOME_URL: /imperium/index.php
 */
const PUBLIC_ROOT = resolvePublicRoot();
const PROJECT_ROOT = PUBLIC_ROOT.replace(/\/public$/, "");
const API_LOGIN_URL = `${PUBLIC_ROOT}/api/auth/login.php`;
const HOME_URL = `${PROJECT_ROOT || ""}/index.php`;

// ===== FUNÇÃO: PROCESSAR LOGIN NO BACKEND PHP =====
/**
 * Envia token JWT do Firebase para backend PHP criar sessão.
 * 
 * Arquitetura híbrida (Firebase + PHP):
 * - Firebase: autenticação e autorização
 * - PHP/MySQL: dados do usuário e lógica de negócio
 * - JWT: ponte de comunicação segura
 * 
 * Fluxo:
 * 1. Obtém token JWT do Firebase (user.getIdToken)
 * 2. Envia token para /api/auth/login.php
 * 3. Backend valida token com Firebase Admin SDK
 * 4. Backend cria sessão PHP ($_SESSION['logged_in'])
 * 5. Backend busca/cria dados do usuário no MySQL
 * 6. Retorna sucesso + dados do usuário
 * 7. Frontend redireciona para home
 * 
 * @param {Object} user - Usuário autenticado do Firebase
 */
async function processBackendLogin(user) {
  try {
    // ===== OBTER TOKEN JWT DO FIREBASE =====
    /**
     * getIdToken() gera token JWT assinado pelo Firebase.
     * 
     * Token contém:
     * - uid: ID único do usuário
     * - email: email do usuário
     * - email_verified: se email foi verificado
     * - exp: data de expiração (1 hora)
     * 
     * Backend valida assinatura do token com Firebase Admin SDK.
     */
    const idToken = await user.getIdToken();
    
    // ===== ENVIAR TOKEN PARA BACKEND =====
    const response = await fetch(API_LOGIN_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${idToken}`, // Header padrão para JWT
      },
    });
    
    // ===== PROCESSAR RESPOSTA =====
    /**
     * Lê resposta como texto primeiro para debug.
     * PHP pode retornar HTML de erro em vez de JSON.
     */
    const rawResponse = await response.text();
    let result;

    try {
      result = JSON.parse(rawResponse);
    } catch (parseError) {
      /**
       * JSON inválido indica erro no backend:
       * - Warning/Notice do PHP
       * - Fatal error
       * - HTML de erro 500
       */
      console.error("Resposta inesperada do backend:", rawResponse);
      showPopup(
        "Resposta inesperada do servidor. Tente novamente em instantes.",
        "red"
      );
      return;
    }

    // ===== VERIFICAR SUCESSO DO LOGIN =====
    if (response.ok && result.success) {
      /**
       * Sucesso: sessão PHP criada.
       * 
       * Backend fez:
       * - Validou token JWT
       * - Criou $_SESSION['logged_in']
       * - Armazenou dados do usuário na sessão
       * - Sincronizou com MySQL
       */
      showPopup("Login bem-sucedido! Redirecionando...", "green");
      console.log("Login com sucesso:", user);
      
      // Aguarda 2 segundos para usuário ver mensagem
      setTimeout(() => {
        window.location.href = HOME_URL;
      }, 2000);
    } else {
      /**
       * Falha: token inválido, expirado ou erro no backend.
       * Usuário precisa tentar novamente.
       */
      showPopup(result.message || "Falha ao iniciar sessão.", "red");
    }
  } catch (error) {
    /**
     * Erro de rede ou servidor offline.
     * Fetch lança erro se não conseguir conectar.
     */
    console.error("Erro ao enviar token para o backend:", error);
    showPopup("Erro na comunicação com o servidor.", "red");
  }
}
