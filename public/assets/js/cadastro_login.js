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

// Botão de login com Google
document
  .getElementById("googleButton")
  .addEventListener("click", signInWithGoogle);

document
  .getElementById("googleButton2")
  .addEventListener("click", signInWithGoogle);

// Evento de submit do formulário de login
loginForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const email = emailInput.value;
  const password = passwordInput.value;
  try {
    const userCredential = await signInWithEmailAndPassword(
      auth,
      email,
      password
    );
    const user = userCredential.user;
    await processBackendLogin(user);
  } catch (error) {
    console.error("Erro de login:", error.code, error.message);
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
    showPopup(errorMessage, "red"); // Modificado
  }
});

// Evento de submit do formulário de cadastro
formCadastro.addEventListener("submit", function (event) {
  event.preventDefault();

  // Pega os valores dentro do event listener
  const email = emailInputCadastro.value.trim();
  const senha = senhaInputCadastro.value;
  const senhaconf = senhaconfInput.value;
  const nome = nomeInputCadastro.value.trim();
  const cpf = cpfInputCadastro.value.trim();
  const telefone = null;
  const dataNasc = null;

  // Verifica se as senhas coincidem
  if (senha !== senhaconf) {
    senhaconfInput.setCustomValidity("As senhas não conferem");
    showPopup("As senhas não coincidem.", "red"); // Modificado
    return;
  } else {
    senhaconfInput.setCustomValidity("");
  }

  // Exibe mensagem de carregamento para o usuário
  showPopup("Processando cadastro...", "blue"); // Modificado

  // 1. Criar o usuário no Firebase Authentication
  createUserWithEmailAndPassword(auth, email, senha)
    .then((userCredential) => {
      const user = userCredential.user;

      // 2. Salvar dados adicionais no Firestore
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
      // 3. Enviar o e-mail de verificação
      const user = auth.currentUser;
      if (user) {
        return sendEmailVerification(user);
      } else {
        throw new Error("Usuário não encontrado após o cadastro.");
      }
    })
    .then(() => {
      // 4. Exibir mensagem de sucesso
      showPopup(
        "Cadastro realizado com sucesso! Um e-mail de verificação foi enviado. Por favor, verifique sua caixa de entrada para ativar sua conta.",
        "green"
      ); // Modificado
      formCadastro.reset();
    })
    .catch((error) => {
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

      showPopup(errorMessage, "red"); // Modificado
      console.error(errorCode, error.message);
    });
});

// Login com Google
async function signInWithGoogle() {
  try {
    const result = await signInWithPopup(auth, googleProvider);
    const user = result.user;
    await processBackendLogin(user);
  } catch (error) {
    const errorCode = error.code;
    const errorMessage = error.message;
    console.error("Erro de login com Google:", errorMessage);
    showPopup("Erro ao fazer login com o Google: " + errorMessage, "red"); // Modificado
  }
}

const resolvePublicRoot = () => {
  const { pathname } = window.location;
  const publicIndex = pathname.indexOf("/public/");
  if (publicIndex === -1) {
    return "";
  }
  return `${pathname.slice(0, publicIndex)}/public`;
};

const PUBLIC_ROOT = resolvePublicRoot();
const PROJECT_ROOT = PUBLIC_ROOT.replace(/\/public$/, "");
const API_LOGIN_URL = `${PUBLIC_ROOT}/api/auth/login.php`;
const HOME_URL = `${PROJECT_ROOT || ""}/index.php`;

// Função para processar o login no backend
async function processBackendLogin(user) {
  try {
    const idToken = await user.getIdToken();
    const response = await fetch(API_LOGIN_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${idToken}`,
      },
    });
    const rawResponse = await response.text();
    let result;

    try {
      result = JSON.parse(rawResponse);
    } catch (parseError) {
      console.error("Resposta inesperada do backend:", rawResponse);
      showPopup(
        "Resposta inesperada do servidor. Tente novamente em instantes.",
        "red"
      );
      return;
    }

    if (response.ok && result.success) {
      showPopup("Login bem-sucedido! Redirecionando...", "green"); // Modificado
      console.log("Login com sucesso:", user);
      setTimeout(() => {
        window.location.href = HOME_URL;
      }, 2000);
    } else {
      showPopup(result.message || "Falha ao iniciar sessão.", "red"); // Modificado
    }
  } catch (error) {
    console.error("Erro ao enviar token para o backend:", error);
    showPopup("Erro na comunicação com o servidor.", "red"); // Modificado
  }
}
