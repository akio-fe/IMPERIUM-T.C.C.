import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import {
  getAuth,
  sendPasswordResetEmail,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// Your web app's Firebase configuration
const firebaseConfig = {
  apiKey: "AIzaSyBtblDahBpfrT4CaLl2viS0D2890iJ_RFE",
  authDomain: "imperium-0001.firebaseapp.com",
  projectId: "imperium-0001",
  storageBucket: "imperium-0001.firebasestorage.app",
  messagingSenderId: "961834611988",
  appId: "1:961834611988:web:0a2ad6089630324094be01",
  measurementId: "G-M39V86RLKS",
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

document.addEventListener("DOMContentLoaded", function () {
  const passwordResetForm = document.getElementById("passwordResetForm");
  const emailInput = document.getElementById("email");
  const mensagemFirebase = document.getElementById("mensagem-firebase");

  passwordResetForm.addEventListener("submit", function (event) {
    event.preventDefault();
    
    // Validate the email field
    if (!emailInput.value) {
      mensagemFirebase.textContent = "Por favor, insira um endereço de e-mail.";
      mensagemFirebase.className = "form-message error";
      return;
    }

    const emailAddress = emailInput.value;
    
    // Clear previous messages
    mensagemFirebase.textContent = "";
    
    sendPasswordResetEmail(auth, emailAddress)
      .then(() => {
        // Password reset email sent!
        mensagemFirebase.textContent =
          "Um link de redefinição de senha foi enviado para o seu e-mail!";
        mensagemFirebase.className = "form-message success";
      })
      .catch((error) => {
        const errorCode = error.code;
        console.error(errorCode, error.message);
        
        let userMessage =
          "Ocorreu um erro. Por favor, tente novamente.";
          
        if (errorCode === "auth/user-not-found") {
          userMessage = "Não há nenhum usuário registrado com este e-mail.";
        } else if (errorCode === "auth/invalid-email") {
          userMessage = "O e-mail fornecido é inválido.";
        }
        
        mensagemFirebase.textContent = userMessage;
        mensagemFirebase.className = "form-message error";
      });
  });
});