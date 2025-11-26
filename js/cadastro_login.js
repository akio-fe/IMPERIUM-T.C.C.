// Importações do Firebase App (necessário para inicializar o Firebase)
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.6.1/firebase-app.js";

// Importações do Firebase Authentication
import {
  getAuth,
  createUserWithEmailAndPassword,
  sendEmailVerification,
  signInWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
} from "https://www.gstatic.com/firebasejs/9.6.1/firebase-auth.js";

// Importações do Firestore
import {
  getFirestore,
  doc,
  setDoc,
} from "https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore.js"; // <--- Importação corrigida aqui!

// Firebase configuration para web app
const firebaseConfig = {
  apiKey: "AIzaSyBtblDahBpfrT4CaLl2viS0D2890iJ_RFE",
  authDomain: "imperium-0001.firebaseapp.com",
  projectId: "imperium-0001",
  storageBucket: "imperium-0001.firebasestorage.app",
  messagingSenderId: "961834611988",
  appId: "1:961834611988:web:0a2ad6089630324094be01",
  measurementId: "G-M39V86RLKS",
};

// Inicializando o Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const googleProvider = new GoogleAuthProvider();
const db = getFirestore(app);

// Criação das variáveis do Formulário de login
const loginForm = document.getElementById("formLogin");
const emailInput = document.getElementById("email-login");
const passwordInput = document.getElementById("senha-login");

// Criação das variáveis do Formulário de cadastro
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

// Função para processar o login no backend
async function processBackendLogin(user) {
  try {
    const idToken = await user.getIdToken();
    const response = await fetch("../php/login_handler.php", {
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
        window.location.href = "../index.php";
      }, 2000);
    } else {
      showPopup(result.message || "Falha ao iniciar sessão.", "red"); // Modificado
    }
  } catch (error) {
    console.error("Erro ao enviar token para o backend:", error);
    showPopup("Erro na comunicação com o servidor.", "red"); // Modificado
  }
}
